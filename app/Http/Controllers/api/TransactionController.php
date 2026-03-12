<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['user:id,name,email', 'business:id,name', 'items'])
            ->orderByDesc('created_at');

        if ($request->filled('user_id'))     $query->where('user_id', $request->user_id);
        if ($request->filled('business_id')) $query->where('business_id', $request->business_id);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('start_date'))  $query->whereDate('created_at', '>=', $request->start_date);
        if ($request->filled('end_date'))    $query->whereDate('created_at', '<=', $request->end_date);

        if ($request->filled('period')) {
            match ($request->period) {
                'weekly'  => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'monthly' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
                default   => null,
            };
        }

        $perPage      = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);

        // Summary — clone query sebelum paginate
        $summaryQuery = Transaction::query();
        if ($request->filled('user_id'))     $summaryQuery->where('user_id', $request->user_id);
        if ($request->filled('business_id')) $summaryQuery->where('business_id', $request->business_id);
        if ($request->filled('start_date'))  $summaryQuery->whereDate('created_at', '>=', $request->start_date);
        if ($request->filled('end_date'))    $summaryQuery->whereDate('created_at', '<=', $request->end_date);
        if ($request->filled('period')) {
            match ($request->period) {
                'weekly'  => $summaryQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'monthly' => $summaryQuery->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
                default   => null,
            };
        }

        return response()->json([
            'data' => $transactions->through(fn($t) => $this->transform($t)),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
                'per_page'     => $transactions->perPage(),
                'total'        => $transactions->total(),
            ],
            'summary' => [
                'total_revenue'   => (int) $summaryQuery->clone()->where('status', 'paid')->sum('total'),
                'total_cancelled' => $summaryQuery->clone()->where('status', 'cancelled')->count(),
                'total_all'       => $summaryQuery->count(),
            ],
        ]);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['user:id,name,email', 'business:id,name', 'items']);
        return response()->json(['data' => $this->transform($transaction)]);
    }

    public function cancel(Request $request, Transaction $transaction)
    {
        if ($transaction->status === 'cancelled') {
            return response()->json(['message' => 'Transaksi sudah dibatalkan.'], 422);
        }

        $request->validate(['reason' => 'nullable|string|max:500']);

        $transaction->update([
            'status'        => 'cancelled',
            'cancel_reason' => $request->reason ?? 'Dibatalkan oleh kasir',
            'cancelled_at'  => now(),
        ]);

        return response()->json([
            'message' => 'Transaksi berhasil dibatalkan.',
            'data'    => $this->transform($transaction->fresh(['user', 'business', 'items'])),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'business_id'                   => 'nullable|exists:businesses,id',
            'payment_method'                => 'required|in:cash,qris,transfer,card',
            'notes'                         => 'nullable|string',
            'discount'                      => 'nullable|integer|min:0',
            'items'                         => 'required|array|min:1',
            'items.*.source'                => 'nullable|in:pos,gvi',
            'items.*.product_id'            => 'nullable|exists:products,id',
            'items.*.gvi_item_variant_id'   => 'nullable|integer',
            'items.*.gvi_item_variant_name' => 'nullable|string',
            'items.*.product_name'          => 'required|string',
            'items.*.price'                 => 'required|integer|min:0',
            'items.*.quantity'              => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $items    = $request->items;
            $subtotal = collect($items)->sum(fn($i) => $i['price'] * $i['quantity']);
            $discount = 0; // diskon sudah baked-in di price tiap item dari Flutter
            $tax      = $request->tax ?? 0;
            $total    = $subtotal + $tax;

            $transaction = Transaction::create([
                'invoice_number' => Transaction::generateInvoiceNumber(),
                'user_id'        => $request->user()->id,
                'business_id'    => $request->business_id,
                'subtotal'       => $subtotal,
                'tax'            => $tax,
                'discount'       => $discount,
                'total'          => $total,
                'payment_method' => $request->payment_method,
                'status'         => 'pending',
                'notes'          => $request->notes,
            ]);

            foreach ($items as $item) {
                $transaction->items()->create([
                    'product_id'            => $item['product_id'] ?? null,
                    'gvi_item_variant_id'   => $item['gvi_item_variant_id'] ?? null,
                    'gvi_item_variant_name' => $item['gvi_item_variant_name'] ?? null,
                    'product_name'          => $item['product_name'],
                    'product_sku'           => $item['product_sku'] ?? null,
                    'price'                 => $item['price'],
                    'quantity'              => $item['quantity'],
                    'subtotal'              => $item['price'] * $item['quantity'],
                    'source'                => $item['source'] ?? 'pos',
                ]);
            }

            // Jika cash: langsung paid
            if ($request->payment_method === 'cash') {
                $transaction->update(['status' => 'paid', 'paid_at' => now()]);
                DB::commit();
                return response()->json([
                    'message'    => 'Transaksi berhasil.',
                    'data'       => $this->transform($transaction->load(['user', 'business', 'items'])),
                    'snap_token' => null,
                ], 201);
            }

            // Non-cash: buat Snap Token Midtrans
            $snapToken = $this->createMidtransSnapToken($transaction, $items);
            $transaction->update([
                'midtrans_order_id'   => $transaction->invoice_number,
                'midtrans_snap_token' => $snapToken,
            ]);

            DB::commit();
            return response()->json([
                'message'    => 'Transaksi berhasil dibuat.',
                'data'       => $this->transform($transaction->load(['user', 'business', 'items'])),
                'snap_token' => $snapToken,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Transaction store error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal menyimpan transaksi.', 'error' => $e->getMessage()], 500);
        }
    }

    public function webhook(Request $request)
    {
        $orderId           = $request->order_id;
        $statusCode        = $request->status_code;
        $grossAmount       = $request->gross_amount;
        $signatureKey      = $request->signature_key;
        $transactionStatus = $request->transaction_status;
        $midtransTransId   = $request->transaction_id;

        $serverKey = config('services.midtrans.server_key');
        $expected  = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($signatureKey !== $expected) {
            return response()->json(['message' => 'Signature tidak valid.'], 403);
        }

        $transaction = Transaction::where('midtrans_order_id', $orderId)->first();
        if (!$transaction) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        if (in_array($transactionStatus, ['capture', 'settlement'])) {
            $transaction->update([
                'status'                  => 'paid',
                'midtrans_transaction_id' => $midtransTransId,
                'paid_at'                 => now(),
            ]);
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $transaction->update(['status' => 'cancelled']);
        }

        return response()->json(['message' => 'OK']);
    }

    public function todaySummary()
    {
        $today = today();
        return response()->json([
            'data' => [
                'total_revenue'      => Transaction::whereDate('created_at', $today)->where('status', 'paid')->sum('total'),
                'total_transactions' => Transaction::whereDate('created_at', $today)->where('status', 'paid')->count(),
                'date'               => $today->toDateString(),
            ],
        ]);
    }

    private function createMidtransSnapToken(Transaction $transaction, array $items): ?string
    {
        \Midtrans\Config::$serverKey    = env('MIDTRANS_SERVER_KEY');
        \Midtrans\Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false) === 'true';
        \Midtrans\Config::$isSanitized  = true;
        \Midtrans\Config::$is3ds        = true;

        $itemDetails = array_map(fn($item) => [
            'id'       => $item['product_id'] ?? 'gvi-' . ($item['gvi_item_variant_id'] ?? 0),
            'price'    => $item['price'],
            'quantity' => $item['quantity'],
            'name'     => substr($item['product_name'], 0, 50),
        ], $items);

        try {
            return \Midtrans\Snap::getSnapToken([
                'transaction_details' => [
                    'order_id'     => $transaction->invoice_number,
                    'gross_amount' => $transaction->total,
                ],
                'item_details'     => $itemDetails,
                'customer_details' => [
                    'first_name' => $transaction->user->name,
                    'email'      => $transaction->user->email,
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Midtrans Snap error: ' . $e->getMessage());
            return null;
        }
    }

    public function export(Request $request)
    {
        // Auth via query token (karena dibuka di browser)
        $tokenValue = $request->query('token');
        if ($tokenValue) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenValue);
            if (!$token) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            auth()->setUser($token->tokenable);
        } elseif (!auth('sanctum')->check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $format = $request->get('format', 'excel');

        $query = Transaction::with(['user:id,name', 'business:id,name', 'items'])
            ->orderByDesc('created_at');

        if ($request->filled('user_id'))     $query->where('user_id', $request->user_id);
        if ($request->filled('business_id')) $query->where('business_id', $request->business_id);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('start_date'))  $query->whereDate('created_at', '>=', $request->start_date);
        if ($request->filled('end_date'))    $query->whereDate('created_at', '<=', $request->end_date);
        if ($request->filled('period')) {
            match ($request->period) {
                'weekly'  => $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'monthly' => $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
                default   => null,
            };
        }

        $transactions = $query->get();

        if ($format === 'pdf') {
            return $this->exportPdf($transactions);
        }
        return $this->exportCsv($transactions);
    }

    private function transform(Transaction $t): array
    {
        return [
            'id'             => $t->id,
            'invoice_number' => $t->invoice_number,
            'status'         => $t->status,
            'payment_method' => $t->payment_method,
            'subtotal'       => $t->subtotal,
            'tax'            => $t->tax,
            'discount'       => $t->discount,
            'total'          => $t->total,
            'cancel_reason'  => $t->cancel_reason,
            'notes'          => $t->notes,
            'paid_at'        => $t->paid_at?->toISOString(),
            'cancelled_at'   => $t->cancelled_at?->toISOString(),
            'created_at'     => $t->created_at->toISOString(),
            'kasir'          => $t->user   ? ['id' => $t->user->id,     'name' => $t->user->name,     'email' => $t->user->email]     : null,
            'business'       => $t->business ? ['id' => $t->business->id, 'name' => $t->business->name] : null,
            'items'          => $t->items->map(fn($i) => [
                'id'           => $i->id,
                'product_name' => $i->product_name,
                'price'        => $i->price,
                'quantity'     => $i->quantity,
                'subtotal'     => $i->subtotal,
                'source'       => $i->source,
            ])->toArray(),
        ];
    }

    private function exportCsv($transactions)
    {
        $filename = 'transaksi-' . now()->format('Ymd-His') . '.csv';
        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
            fputcsv($file, ['No','Invoice','Tanggal','Kasir','Bisnis','Metode','Status','Subtotal','Pajak','Diskon','Total','Alasan Batal','Item']);
            foreach ($transactions as $i => $t) {
                $items = $t->items->map(fn($item) => "{$item->product_name} x{$item->quantity}")->join('; ');
                fputcsv($file, [
                    $i + 1, $t->invoice_number,
                    $t->created_at->format('d/m/Y H:i'),
                    $t->user?->name ?? '-',
                    $t->business?->name ?? '-',
                    strtoupper($t->payment_method),
                    strtoupper($t->status),
                    $t->subtotal, $t->tax, $t->discount, $t->total,
                    $t->cancel_reason ?? '-',
                    $items,
                ]);
            }
            fclose($file);
        };
        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }

    private function exportPdf($transactions)
    {
        $total_revenue = $transactions->where('status', 'paid')->sum('total');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;font-size:11px;margin:20px}
h2{text-align:center;margin-bottom:4px}
.sub{text-align:center;color:#666;margin-bottom:16px}
table{width:100%;border-collapse:collapse}
th{background:#1d4ed8;color:white;padding:6px 8px;text-align:left}
td{padding:5px 8px;border-bottom:1px solid #e2e8f0}
tr:nth-child(even){background:#f8fafc}
.paid{color:#16a34a;font-weight:bold}
.cancelled{color:#dc2626;font-weight:bold}
.summary{margin-top:16px;text-align:right;font-weight:bold}
</style></head><body>
<h2>Laporan Transaksi GVI POS</h2>
<div class="sub">Dicetak: ' . now()->format('d/m/Y H:i') . '</div>
<table>
<tr><th>No</th><th>Invoice</th><th>Tanggal</th><th>Kasir</th><th>Bisnis</th><th>Metode</th><th>Status</th><th>Total</th><th>Alasan Batal</th></tr>';

        foreach ($transactions as $i => $t) {
            $statusClass = $t->status === 'paid' ? 'paid' : 'cancelled';
            $html .= '<tr>
                <td>' . ($i + 1) . '</td>
                <td>' . $t->invoice_number . '</td>
                <td>' . $t->created_at->format('d/m/Y H:i') . '</td>
                <td>' . ($t->user?->name ?? '-') . '</td>
                <td>' . ($t->business?->name ?? '-') . '</td>
                <td>' . strtoupper($t->payment_method) . '</td>
                <td class="' . $statusClass . '">' . strtoupper($t->status) . '</td>
                <td>Rp ' . number_format($t->total, 0, ',', '.') . '</td>
                <td>' . ($t->cancel_reason ?? '-') . '</td>
            </tr>';
        }

        $html .= '</table>
<div class="summary">Total Pendapatan: Rp ' . number_format($total_revenue, 0, ',', '.') . '</div>
</body></html>';

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
