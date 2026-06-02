<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpenBillController extends Controller
{
    // GET /open-bill — list semua open bill by business
    public function index(Request $request)
    {
        $bills = Transaction::with('items')
            ->where('business_id', $request->user()->business_id)
            ->where('status', 'open_bill')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($t) => [
                'id'            => $t->id,
                'invoice_number'=> $t->invoice_number,
                'customer_name' => $t->customer_name,
                'table_number'  => $t->table_number,
                'total'         => $t->total,
                'item_count'    => $t->items->sum('quantity'),
                'created_at'    => $t->created_at,
            ]);

        return response()->json($bills);
    }

    // GET /open-bill/{id} — detail items grouped by order_sequence
    public function show($id, Request $request)
    {
        $bill = Transaction::with('items.product')
            ->where('business_id', $request->user()->business_id)
            ->where('status', 'open_bill')
            ->findOrFail($id);

        $grouped = $bill->items
            ->groupBy('order_sequence')
            ->map(fn($items, $seq) => [
                'order_sequence' => $seq,
                'ordered_at'     => $items->first()->ordered_at,
                'items'          => $items->values(),
            ])
            ->values();

        return response()->json([
            'id'             => $bill->id,
            'invoice_number' => $bill->invoice_number,
            'customer_name'  => $bill->customer_name,
            'table_number'   => $bill->table_number,
            'subtotal'       => $bill->subtotal,
            'tax'            => $bill->tax,
            'total'          => $bill->total,
            'orders'         => $grouped,
        ]);
    }

    // POST /open-bill — buat open bill baru
    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'nullable|string|max:100',
            'table_number'  => 'nullable|string|max:20',
            'items'         => 'required|array|min:1',
            'items.*.product_id'   => 'nullable|integer',
            'items.*.product_name' => 'required|string',
            'items.*.product_sku'  => 'nullable|string',
            'items.*.price'        => 'required|integer',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.subtotal'     => 'required|integer',
            'subtotal'      => 'required|integer',
            'tax'           => 'required|integer',
            'total'         => 'required|integer',
        ]);

        $bill = DB::transaction(function () use ($request) {
            $transaction = Transaction::create([
                'invoice_number' => Transaction::generateInvoiceNumber(),
                'user_id'        => $request->user()->id,
                'business_id'    => $request->user()->business_id,
                'customer_name'  => $request->customer_name,
                'table_number'   => $request->table_number,
                'subtotal'       => $request->subtotal,
                'tax'            => $request->tax,
                'discount'       => 0,
                'total'          => $request->total,
                'status'         => 'open_bill',
                'payment_method' => 'cash',
                'queue_color'    => Transaction::assignQueueColor($request->user()->business_id),
            ]);

            $now = now();
            foreach ($request->items as $item) {
                TransactionItem::create([
                    'transaction_id'  => $transaction->id,
                    'product_id'      => $item['product_id'] ?? null,
                    'product_name'    => $item['product_name'],
                    'product_sku'     => $item['product_sku'] ?? null,
                    'price'           => $item['price'],
                    'quantity'        => $item['quantity'],
                    'subtotal'        => $item['subtotal'],
                    'order_sequence'  => 1,
                    'ordered_at'      => $now,
                ]);
            }

            return $transaction->load('items');
        });

        return response()->json($bill, 201);
    }

    // POST /open-bill/{id}/append — tambah items ke open bill yang sama
    public function append($id, Request $request)
    {
        $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id'   => 'nullable|integer',
            'items.*.product_name' => 'required|string',
            'items.*.product_sku'  => 'nullable|string',
            'items.*.price'        => 'required|integer',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.subtotal'     => 'required|integer',
        ]);

        $bill = Transaction::where('business_id', $request->user()->business_id)
            ->where('status', 'open_bill')
            ->findOrFail($id);

        DB::transaction(function () use ($bill, $request) {
            $nextSeq = $bill->items()->max('order_sequence') + 1;
            $now     = now();

            foreach ($request->items as $item) {
                TransactionItem::create([
                    'transaction_id'  => $bill->id,
                    'product_id'      => $item['product_id'] ?? null,
                    'product_name'    => $item['product_name'],
                    'product_sku'     => $item['product_sku'] ?? null,
                    'price'           => $item['price'],
                    'quantity'        => $item['quantity'],
                    'subtotal'        => $item['subtotal'],
                    'order_sequence'  => $nextSeq,
                    'ordered_at'      => $now,
                ]);
            }

            // Recalculate total
            $bill->subtotal = $bill->items()->sum('subtotal');
            $bill->total    = $bill->subtotal + $bill->tax;
            $bill->save();
        });

        return response()->json($bill->load('items'));
    }

    // POST /open-bill/{id}/pay — bayar open bill
    public function pay($id, Request $request)
    {
        $request->validate([
            'payment_method' => 'required|in:cash,qris,transfer,card',
            'discount'       => 'nullable|integer',
        ]);

        $bill = Transaction::where('business_id', $request->user()->business_id)
            ->where('status', 'open_bill')
            ->findOrFail($id);

        DB::transaction(function () use ($bill, $request) {
            $discount    = $request->discount ?? 0;
            $bill->status         = 'paid';
            $bill->payment_method = $request->payment_method;
            $bill->discount       = $discount;
            $bill->total          = $bill->subtotal + $bill->tax - $discount;
            $bill->paid_at        = now();
            $bill->save();
        });

        return response()->json($bill->load('items'));
    }

    public function destroy($id, Request $request)
    {
        $bill = Transaction::where('business_id', $request->user()->business_id)
            ->where('status', 'open_bill')
            ->findOrFail($id);

        $bill->cancel_reason = 'Dibatalkan oleh kasir';
        $bill->status = 'cancelled';
        $bill->cancelled_at = now();
        $bill->save();

        return response()->json(['message' => 'Open bill dibatalkan']);
    }
}
