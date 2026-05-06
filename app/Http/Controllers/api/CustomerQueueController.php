<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;

class CustomerQueueController extends Controller
{
    // ─────────────────────────────────────────────
    // GET /customer-queue
    // ─────────────────────────────────────────────
    public function index()
    {
        $transactions = Transaction::with(['items:id,transaction_id,kitchen_status'])
            ->where('status', 'paid')
            ->whereIn('queue_status', ['waiting', 'ready'])
            ->whereNotNull('table_number') // takeaway ga masuk antrian layar
            ->orderBy('paid_at', 'asc')
            ->get()
            ->map(fn($t) => $this->transform($t));

        return response()->json(['data' => $transactions]);
    }

    // ─────────────────────────────────────────────
    // PATCH /customer-queue/{id}/taken
    // ─────────────────────────────────────────────
    public function taken(int $id)
    {
        $transaction = Transaction::findOrFail($id);

        if ($transaction->queue_status !== 'ready') {
            return response()->json([
                'message' => 'Pesanan belum ready, tidak bisa diambil.'
            ], 422);
        }

        $transaction->update(['queue_status' => 'taken']);

        return response()->json(['data' => $this->transform($transaction)]);
    }

    // ─────────────────────────────────────────────
    // Transform
    // ─────────────────────────────────────────────
    private function transform(Transaction $t): array
    {
        $totalItems  = $t->items->count();
        $doneItems   = $t->items->where('kitchen_status', 'done')->count();

        return [
            'id'             => $t->id,
            'invoice_number' => $t->invoice_number,
            'table_number'   => $t->table_number,
            'queue_status'   => $t->queue_status,
            'ready_at'       => $t->ready_at?->toISOString(),
            'paid_at'        => $t->paid_at?->toISOString(),
            'total_items'    => $totalItems,
            'done_items'     => $doneItems,
            // progress: buat progress bar di frontend (misal 2/4 item selesai)
        ];
    }
}