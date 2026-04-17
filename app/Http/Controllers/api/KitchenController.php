<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionItem;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    // ─────────────────────────────────────────────
    // GET /kitchen/queue
    // Ambil semua item yang belum done, ordered by waktu transaksi masuk
    // ─────────────────────────────────────────────
    public function queue(Request $request)
    {
        $items = TransactionItem::with(['transaction:id,invoice_number,created_at', 'product:id,image'])
            ->whereHas('transaction', fn($q) => $q->where('status', 'paid'))
            ->whereIn('kitchen_status', ['queued', 'cooking', 'paused'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($i) => $this->transform($i));

        return response()->json(['data' => $items]);
    }

    // ─────────────────────────────────────────────
    // PATCH /kitchen/items/{id}/start
    // Mulai masak (queued → cooking) atau lanjut (paused → cooking)
    // ─────────────────────────────────────────────
    public function start(int $id)
    {
        $item = TransactionItem::findOrFail($id);

        if (!in_array($item->kitchen_status, ['queued', 'paused'])) {
            return response()->json(['message' => 'Item tidak bisa distart dari status: ' . $item->kitchen_status], 422);
        }

        $item->update([
            'kitchen_status'     => 'cooking',
            'cooking_started_at' => now(),
        ]);

        return response()->json(['data' => $this->transform($item->fresh(['transaction', 'product']))]);
    }

    // ─────────────────────────────────────────────
    // PATCH /kitchen/items/{id}/pause
    // Pause timer (cooking → paused), akumulasi durasi pause
    // ─────────────────────────────────────────────
    public function pause(int $id)
    {
        $item = TransactionItem::findOrFail($id);

        // Validasi harus lagi cooking
        if ($item->kitchen_status !== 'cooking') {
            return response()->json([
                'message' => 'Item tidak sedang dimasak.'
            ], 422);
        }

        // Hitung durasi sejak terakhir start (kalau ada)
        $cookingSinceLastStart = $item->cooking_started_at
            ? now()->diffInSeconds($item->cooking_started_at)
            : 0;

        // Total durasi = sebelumnya + session sekarang
        $totalCookingDuration =
            ($item->cooking_duration_seconds ?? 0) + $cookingSinceLastStart;

        // Update state
        $item->update([
            'kitchen_status'           => 'paused',
            'cooking_duration_seconds' => $totalCookingDuration,

            // 🔥 WAJIB: reset biar ga double count pas resume
            'cooking_started_at'       => null,
        ]);

        return response()->json([
            'data' => $this->transform(
                $item->fresh(['transaction', 'product'])
            )
        ]);
    }

    // ─────────────────────────────────────────────
    // PATCH /kitchen/items/{id}/done
    // Selesai masak, finalize semua durasi
    // ─────────────────────────────────────────────
    public function done(int $id)
    {
        $item = TransactionItem::findOrFail($id);

        if (!in_array($item->kitchen_status, ['cooking', 'paused'])) {
            return response()->json(['message' => 'Item tidak bisa diselesaikan dari status: ' . $item->kitchen_status], 422);
        }

        $now = now();

        // Kalau masih cooking saat di-done, hitung sisa durasi sejak start terakhir
        $additionalSeconds = 0;
        if ($item->kitchen_status === 'cooking' && $item->cooking_started_at) {
            $additionalSeconds = $now->diffInSeconds($item->cooking_started_at);
        }

        // Total pause = selisih total elapsed - cooking duration
        // cooking_started_at pertama kali sampai done, minus waktu cooking bersih
        $totalCooking = ($item->cooking_duration_seconds ?? 0) + $additionalSeconds;

        $item->update([
            'kitchen_status'           => 'done',
            'cooking_done_at'          => $now,
            'cooking_duration_seconds' => $totalCooking,
        ]);

        return response()->json(['data' => $this->transform($item->fresh(['transaction', 'product']))]);
    }

    // ─────────────────────────────────────────────
    // Transform item untuk response
    // ─────────────────────────────────────────────
    private function transform(TransactionItem $i): array
    {
        return [
            'id'                       => $i->id,
            'product_name'             => $i->product_name,
            'product_sku'              => $i->product_sku,
            'quantity'                 => $i->quantity,
            'source'                   => $i->source,
            'kitchen_status'           => $i->kitchen_status,
            'cooking_started_at'       => $i->cooking_started_at?->toISOString(),
            'cooking_done_at'          => $i->cooking_done_at?->toISOString(),
            'cooking_duration_seconds' => $i->cooking_duration_seconds,
            'pause_duration_seconds'   => $i->pause_duration_seconds,
            'transaction' => $i->transaction ? [
                'id'             => $i->transaction->id,
                'invoice_number' => $i->transaction->invoice_number,
                'created_at'     => $i->transaction->created_at->toISOString(),
            ] : null,
        ];
    }
}
