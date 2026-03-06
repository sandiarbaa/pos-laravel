<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    // List transaksi
    public function index(Request $request)
    {
        $query = Transaction::with(['user', 'business', 'items'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $transactions = $query->paginate($request->get('per_page', 20));

        return response()->json($transactions);
    }

    // Buat transaksi baru & dapatkan Snap Token Midtrans
    public function store(Request $request)
    {
        $request->validate([
            'business_id'    => 'nullable|exists:businesses,id',
            'payment_method' => 'required|in:cash,qris,transfer,card',
            'notes'          => 'nullable|string',
            'discount'       => 'nullable|integer|min:0',
            'items'          => 'required|array|min:1',
            'items.*.source' => 'required|in:pos,gvi',

            // Item dari POS
            'items.*.product_id' => 'required_if:items.*.source,pos|nullable|exists:products,id',

            // Item dari GVI
            'items.*.gvi_item_variant_id'   => 'required_if:items.*.source,gvi|nullable|integer',
            'items.*.gvi_item_variant_name' => 'required_if:items.*.source,gvi|nullable|string',

            'items.*.product_name' => 'required|string',
            'items.*.price'        => 'required|integer|min:0',
            'items.*.quantity'     => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $items    = $request->items;
            $subtotal = collect($items)->sum(fn($i) => $i['price'] * $i['quantity']);
            $discount = $request->discount ?? 0;
            $tax      = 0; // bisa dikembangkan
            $total    = $subtotal - $discount + $tax;

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
                TransactionItem::create([
                    'transaction_id'        => $transaction->id,
                    'product_id'            => $item['product_id'] ?? null,
                    'gvi_item_variant_id'   => $item['gvi_item_variant_id'] ?? null,
                    'gvi_item_variant_name' => $item['gvi_item_variant_name'] ?? null,
                    'product_name'          => $item['product_name'],
                    'product_sku'           => $item['product_sku'] ?? null,
                    'price'                 => $item['price'],
                    'quantity'              => $item['quantity'],
                    'subtotal'              => $item['price'] * $item['quantity'],
                    'source'                => $item['source'],
                ]);
            }

            // Jika payment method bukan cash, buat Snap Token Midtrans
            $snapToken = null;
            if ($request->payment_method !== 'cash') {
                $snapToken = $this->createMidtransSnapToken($transaction, $items);
                $transaction->update([
                    'midtrans_order_id'    => $transaction->invoice_number,
                    'midtrans_snap_token'  => $snapToken,
                ]);
            }

            DB::commit();

            $transaction->load(['user', 'business', 'items']);

            return response()->json([
                'message'    => 'Transaksi berhasil dibuat.',
                'data'       => $transaction,
                'snap_token' => $snapToken,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Transaction store error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal membuat transaksi.', 'error' => $e->getMessage()], 500);
        }
    }

    // Detail transaksi
    public function show($id)
    {
        $transaction = Transaction::with(['user', 'business', 'items'])->findOrFail($id);
        return response()->json(['data' => $transaction]);
    }

    // Cancel transaksi
    public function cancel($id)
    {
        $transaction = Transaction::findOrFail($id);

        if ($transaction->status === 'paid') {
            return response()->json(['message' => 'Transaksi yang sudah dibayar tidak bisa dibatalkan.'], 422);
        }

        $transaction->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Transaksi berhasil dibatalkan.', 'data' => $transaction]);
    }

    // Webhook dari Midtrans — update status transaksi & update stok GVI
    public function webhook(Request $request)
    {
        $orderId           = $request->order_id;
        $statusCode        = $request->status_code;
        $grossAmount       = $request->gross_amount;
        $signatureKey      = $request->signature_key;
        $transactionStatus = $request->transaction_status;
        $midtransTransId   = $request->transaction_id;

        // Verifikasi signature Midtrans
        $serverKey = config('services.midtrans.server_key');
        $expected  = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($signatureKey !== $expected) {
            return response()->json(['message' => 'Signature tidak valid.'], 403);
        }

        $transaction = Transaction::where('midtrans_order_id', $orderId)->first();
        if (!$transaction) {
            return response()->json(['message' => 'Transaksi tidak ditemukan.'], 404);
        }

        // Update status berdasarkan notifikasi Midtrans
        if (in_array($transactionStatus, ['capture', 'settlement'])) {
            $transaction->update([
                'status'                  => 'paid',
                'midtrans_transaction_id' => $midtransTransId,
                'paid_at'                 => now(),
            ]);

            // Update stok GVI-Stock untuk item yang bersumber dari GVI
            $gviItems = $transaction->items()->where('source', 'gvi')->get();
            foreach ($gviItems as $item) {
                GviStockController::decreaseStock($item->gvi_item_variant_id, $item->quantity);
            }

            // Kurangi stok produk POS
            $posItems = $transaction->items()->where('source', 'pos')->get();
            foreach ($posItems as $item) {
                if ($item->product_id) {
                    Product::where('id', $item->product_id)
                        ->decrement('stock', $item->quantity);
                }
            }
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $transaction->update(['status' => 'cancelled']);
        }

        return response()->json(['message' => 'OK']);
    }

    // Summary hari ini
    public function todaySummary()
    {
        $today = today();

        $total = Transaction::whereDate('created_at', $today)
            ->where('status', 'paid')
            ->sum('total');

        $count = Transaction::whereDate('created_at', $today)
            ->where('status', 'paid')
            ->count();

        return response()->json([
            'data' => [
                'total_revenue'      => $total,
                'total_transactions' => $count,
                'date'               => $today->toDateString(),
            ],
        ]);
    }

    // Buat Snap Token Midtrans
    private function createMidtransSnapToken(Transaction $transaction, array $items): ?string
    {
        \Midtrans\Config::$serverKey    = config('services.midtrans.server_key');
        \Midtrans\Config::$isProduction = config('services.midtrans.is_production', false);
        \Midtrans\Config::$isSanitized  = true;
        \Midtrans\Config::$is3ds        = true;

        $itemDetails = array_map(fn($item) => [
            'id'       => $item['product_id'] ?? 'gvi-' . ($item['gvi_item_variant_id'] ?? 0),
            'price'    => $item['price'],
            'quantity' => $item['quantity'],
            'name'     => substr($item['product_name'], 0, 50),
        ], $items);

        $params = [
            'transaction_details' => [
                'order_id'     => $transaction->invoice_number,
                'gross_amount' => $transaction->total,
            ],
            'item_details'  => $itemDetails,
            'customer_details' => [
                'first_name' => $transaction->user->name,
                'email'      => $transaction->user->email,
            ],
        ];

        try {
            return \Midtrans\Snap::getSnapToken($params);
        } catch (\Exception $e) {
            Log::error('Midtrans Snap error: ' . $e->getMessage());
            return null;
        }
    }
}
