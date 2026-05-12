<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class CustomerQueueController extends Controller
{
    public function index(Request $request)
    {
        $businessId = $request->user()->business_id;

        $transactions = Transaction::with(['items:id,transaction_id,product_name,quantity,kitchen_status'])
            ->where('status', 'paid')
            ->whereIn('queue_status', ['waiting', 'ready'])
            // hapus whereNotNull — takeaway (null/0) sekarang ikut masuk
            ->where('business_id', $businessId)
            ->whereDate('paid_at', today())
            ->orderBy('paid_at', 'asc')
            ->get()
            ->map(fn($t) => $this->transform($t));

        return response()->json(['data' => $transactions]);
    }

    public function taken(Request $request, int $id)
    {
        $transaction = Transaction::findOrFail($id);
        $businessId  = $request->user()->business_id;

        if ($transaction->business_id !== $businessId) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($transaction->queue_status !== 'ready') {
            return response()->json([
                'message' => 'Pesanan belum ready, tidak bisa diambil.'
            ], 422);
        }

        $transaction->update(['queue_status' => 'taken']);

        return response()->json(['data' => $this->transform($transaction)]);
    }

    private function transform(Transaction $t): array
    {
        $totalItems = $t->items->count();
        $doneItems  = $t->items->where('kitchen_status', 'done')->count();

        $isTakeaway = is_null($t->table_number) || $t->table_number === '0';

        return [
            'id'             => $t->id,
            'invoice_number' => $t->invoice_number,
            'table_number'   => $t->table_number,
            'order_type'     => $isTakeaway ? 'takeaway' : 'dine_in',
            'queue_status'   => $t->queue_status,
            'ready_at'       => $t->ready_at?->toISOString(),
            'paid_at'        => $t->paid_at?->toISOString(),
            'total_items'    => $totalItems,
            'done_items'     => $doneItems,
            'items' => $t->items->map(fn($item) => [
                    'product_name'   => $item->product_name,
                    'quantity'       => $item->quantity,
                    'kitchen_status' => $item->kitchen_status,
                ])->values(),
        ];
    }
}
