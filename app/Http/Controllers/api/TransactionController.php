<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    // ─────────────────────────────────────────────
    // GET /transactions
    // ─────────────────────────────────────────────
    public function index(Request $request)
    {
        $me       = $request->user();
        $kasirIds = null;

        // Admin hanya lihat transaksi dari kasir miliknya
        if ($me->isAdmin()) {
            $kasirIds = User::where('owner_id', $me->id)->pluck('id');
            // Kalau belum ada kasir → return empty
            if ($kasirIds->isEmpty()) {
                return response()->json([
                    'data' => ['data' => []],
                    'meta' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0],
                    'summary' => ['total_revenue' => 0, 'total_cancelled' => 0, 'total_all' => 0],
                ]);
            }
        }

        $query = Transaction::with(['user:id,name,email', 'business:id,name', 'items'])
            ->orderByDesc('created_at');

        if ($kasirIds) $query->whereIn('user_id', $kasirIds);

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

        // Summary — ikut filter owner
        $summaryQuery = Transaction::query();
        if ($kasirIds) $summaryQuery->whereIn('user_id', $kasirIds);
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

    // ─────────────────────────────────────────────
    // GET /transactions/{id}
    // ─────────────────────────────────────────────
    public function show(Transaction $transaction)
    {
        $transaction->load(['user:id,name,email', 'business:id,name', 'items']);
        return response()->json(['data' => $this->transform($transaction)]);
    }

    // ─────────────────────────────────────────────
    // POST /transactions
    // Transaksi normal (cash langsung paid, non-cash via Midtrans)
    // ─────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'business_id'                   => 'nullable|exists:businesses,id',
            'payment_method'                => 'required|in:cash,qris,transfer,card',
            'notes'                         => 'nullable|string',
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

            $transaction = Transaction::create([
                'invoice_number' => Transaction::generateInvoiceNumber(),
                'user_id'        => $request->user()->id,
                'business_id'    => $request->business_id,
                'subtotal'       => $subtotal,
                'tax'            => 0,
                'discount'       => 0,
                'total'          => $subtotal,
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

            if ($request->payment_method === 'cash') {
                $transaction->update(['status' => 'paid', 'paid_at' => now()]);
                $this->deductStock($items);
                DB::commit();
                return response()->json([
                    'message'    => 'Transaksi berhasil.',
                    'data'       => $this->transform($transaction->load(['user', 'business', 'items'])),
                    'snap_token' => null,
                ], 201);
            }

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

    // ─────────────────────────────────────────────
    // POST /transactions/cancel-direct
    // Kasir batal dari cart — langsung cancelled tanpa jadi "paid" dulu
    // ─────────────────────────────────────────────
    public function storeCancelled(Request $request)
    {
        $request->validate([
            'business_id'          => 'nullable|exists:businesses,id',
            'reason'               => 'nullable|string|max:500',
            'notes'                => 'nullable|string',
            'items'                => 'required|array|min:1',
            'items.*.product_name' => 'required|string',
            'items.*.price'        => 'required|integer|min:0',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.source'       => 'nullable|in:pos,gvi',
        ]);

        DB::beginTransaction();
        try {
            $items    = $request->items;
            $subtotal = collect($items)->sum(fn($i) => $i['price'] * $i['quantity']);

            $transaction = Transaction::create([
                'invoice_number' => Transaction::generateInvoiceNumber(),
                'user_id'        => $request->user()->id,
                'business_id'    => $request->business_id,
                'subtotal'       => $subtotal,
                'tax'            => 0,
                'discount'       => 0,
                'total'          => $subtotal,
                'payment_method' => 'cash',
                'status'         => 'cancelled',
                'cancel_reason'  => $request->reason ?? 'Dibatalkan oleh kasir',
                'cancelled_at'   => now(),
                'notes'          => $request->notes,
            ]);

            foreach ($items as $item) {
                $transaction->items()->create([
                    'product_id'   => $item['product_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'price'        => $item['price'],
                    'quantity'     => $item['quantity'],
                    'subtotal'     => $item['price'] * $item['quantity'],
                    'source'       => $item['source'] ?? 'pos',
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Transaksi dibatalkan dan tercatat.',
                'data'    => $this->transform($transaction->load(['user', 'business', 'items'])),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal mencatat pembatalan.', 'error' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────
    // PUT /transactions/{id}/cancel
    // Cancel dari superadmin di riwayat transaksi
    // ─────────────────────────────────────────────
    public function cancel(Request $request, Transaction $transaction)
    {
        if ($transaction->status === 'cancelled') {
            return response()->json(['message' => 'Transaksi sudah dibatalkan.'], 422);
        }

        $request->validate(['reason' => 'nullable|string|max:500']);

        $transaction->update([
            'status'        => 'cancelled',
            'cancel_reason' => $request->reason ?? 'Dibatalkan oleh superadmin',
            'cancelled_at'  => now(),
        ]);

        return response()->json([
            'message' => 'Transaksi berhasil dibatalkan.',
            'data'    => $this->transform($transaction->fresh(['user', 'business', 'items'])),
        ]);
    }

    // ─────────────────────────────────────────────
    // POST /webhook/midtrans
    // ─────────────────────────────────────────────
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
            // Kurangi stok saat non-cash berhasil dibayar
            $items = $transaction->items->map(fn($i) => [
                'product_id' => $i->product_id,
                'quantity'   => $i->quantity,
                'source'     => $i->source,
            ])->toArray();
            $this->deductStock($items);
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $transaction->update(['status' => 'cancelled']);
        }

        return response()->json(['message' => 'OK']);
    }

    // ─────────────────────────────────────────────
    // GET /transactions/today-summary
    // ─────────────────────────────────────────────
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

    // ─────────────────────────────────────────────
    // GET /transactions/export
    // ─────────────────────────────────────────────
    public function export(Request $request)
    {
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

    // ─────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────
    private function deductStock(array $items): void
    {
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? null;
            $source    = $item['source'] ?? 'pos';
            $qty       = $item['quantity'] ?? 1;

            if ($source === 'pos' && $productId) {
                // Kurangi stok di tabel products POS
                \App\Models\Product::where('id', $productId)
                    ->where('stock', '>', 0)
                    ->decrement('stock', $qty);
            }
            // source 'gvi' — stok di GVI-Stock, belum dihandle di sini
            // bisa ditambah HTTP call ke GVI-Stock API nanti
        }
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
            'kasir'          => $t->user
                ? ['id' => $t->user->id, 'name' => $t->user->name, 'email' => $t->user->email]
                : null,
            'business'       => $t->business
                ? ['id' => $t->business->id, 'name' => $t->business->name]
                : null,
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

    // INI BUAT PRODUCTION
    // private function createMidtransSnapToken(Transaction $transaction, array $items): ?string
    // {
    //     \Illuminate\Support\Facades\Log::info('Midtrans config', [
    //         'server_key'    => substr(config('services.midtrans.server_key'), 0, 10) . '...',
    //         'is_production' => config('services.midtrans.is_production'),
    //     ]);

    //     \Midtrans\Config::$serverKey    = config('services.midtrans.server_key');
    //     \Midtrans\Config::$isProduction = (bool) config('services.midtrans.is_production');
    //     \Midtrans\Config::$isSanitized  = true;
    //     \Midtrans\Config::$is3ds        = true;

    //     $itemDetails = array_map(fn($item) => [
    //         'id'       => $item['product_id'] ?? 'gvi-' . ($item['gvi_item_variant_id'] ?? 0),
    //         'price'    => $item['price'],
    //         'quantity' => $item['quantity'],
    //         'name'     => substr($item['product_name'], 0, 50),
    //     ], $items);

    //     try {
    //         return \Midtrans\Snap::getSnapToken([
    //             'transaction_details' => [
    //                 'order_id'     => $transaction->invoice_number,
    //                 'gross_amount' => $transaction->total,
    //             ],
    //             'item_details'     => $itemDetails,
    //             'customer_details' => [
    //                 'first_name' => $transaction->user->name,
    //                 'email'      => $transaction->user->email,
    //             ],
    //         ]);
    //     } catch (\Exception $e) {
    //         \Illuminate\Support\Facades\Log::error('Midtrans Snap error: ' . $e->getMessage());
    //         return null;
    //     }
    // }

    // INI BUAT LOCAL DEVELOPMENT, SANDBOX MIDTRANS
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

    private function exportCsv($transactions)
    {
        $filename = 'laporan-transaksi-' . now()->format('Ymd-His') . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\TransactionsExport($transactions),
            $filename
        );
    }

        private function exportPdf($transactions)
    {
        $total_revenue = $transactions->where('status', 'paid')->sum('total');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
h2 { text-align: center; margin-bottom: 4px; }
.sub { text-align: center; color: #666; margin-bottom: 16px; }
table { width: 100%; border-collapse: collapse; }
th { background: #1d4ed8; color: white; padding: 6px 8px; text-align: left; }
td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
tr:nth-child(even) { background: #f8fafc; }
.paid { color: #16a34a; font-weight: bold; }
.cancelled { color: #dc2626; font-weight: bold; }
.summary { margin-top: 16px; text-align: right; font-weight: bold; }
</style></head><body>
<h2>Laporan Transaksi GVI POS</h2>
<div class="sub">Dicetak: ' . now()->format('d/m/Y H:i') . '</div>
<table>
<tr>
  <th>No</th><th>Invoice</th><th>Tanggal</th><th>Kasir</th>
  <th>Bisnis</th><th>Metode</th><th>Status</th><th>Total</th><th>Alasan Batal</th>
</tr>';

        foreach ($transactions as $i => $t) {
            $statusClass = $t->status === 'paid' ? 'paid' : 'cancelled';
            $html .= '<tr>
                <td>' . ($i + 1) . '</td>
                <td>' . e($t->invoice_number) . '</td>
                <td>' . $t->created_at->format('d/m/Y H:i') . '</td>
                <td>' . e($t->user?->name ?? '-') . '</td>
                <td>' . e($t->business?->name ?? '-') . '</td>
                <td>' . strtoupper($t->payment_method) . '</td>
                <td class="' . $statusClass . '">' . strtoupper($t->status) . '</td>
                <td>Rp ' . number_format($t->total, 0, ',', '.') . '</td>
                <td>' . e($t->cancel_reason ?? '-') . '</td>
            </tr>';
        }

        $html .= '</table>
<div class="summary">Total Pendapatan: Rp ' . number_format($total_revenue, 0, ',', '.') . '</div>
</body></html>';

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
