<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionItem;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    public function queue(Request $request)
    {
        $items = TransactionItem::with([
                'transaction:id,invoice_number,created_at,table_number',
                'product:id,image,category_id',
                'product.category:id,name,color',
            ])
            ->whereHas('transaction', fn($q) => $q->where('status', 'paid'))
            ->whereIn('kitchen_status', ['queued', 'cooking', 'paused'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($i) => $this->transform($i));

        return response()->json(['data' => $items]);
    }

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

        return response()->json(['data' => $this->transform($item->fresh(['transaction', 'product.category']))]);
    }

    public function pause(int $id)
    {
        $item = TransactionItem::findOrFail($id);

        if ($item->kitchen_status !== 'cooking') {
            return response()->json(['message' => 'Item tidak sedang dimasak.'], 422);
        }

        $cookingSinceLastStart = $item->cooking_started_at
            ? now()->diffInSeconds($item->cooking_started_at)
            : 0;

        $totalCookingDuration = ($item->cooking_duration_seconds ?? 0) + $cookingSinceLastStart;

        $item->update([
            'kitchen_status'           => 'paused',
            'cooking_duration_seconds' => $totalCookingDuration,
            'cooking_started_at'       => null,
        ]);

        return response()->json(['data' => $this->transform($item->fresh(['transaction', 'product.category']))]);
    }

    public function done(int $id)
    {
        $item = TransactionItem::findOrFail($id);

        if (!in_array($item->kitchen_status, ['cooking', 'paused'])) {
            return response()->json(['message' => 'Item tidak bisa diselesaikan dari status: ' . $item->kitchen_status], 422);
        }

        $now = now();
        $additionalSeconds = 0;

        if ($item->kitchen_status === 'cooking' && $item->cooking_started_at) {
            $additionalSeconds = $now->diffInSeconds($item->cooking_started_at);
        }

        $item->update([
            'kitchen_status'           => 'done',
            'cooking_done_at'          => $now,
            'cooking_duration_seconds' => ($item->cooking_duration_seconds ?? 0) + $additionalSeconds,
        ]);

        $transaction = $item->transaction;
        $allDone = $transaction->items()
            ->whereNotIn('kitchen_status', ['done'])
            ->doesntExist();

        if ($allDone && $transaction->queue_status === 'waiting') {
            $transaction->update([
                'queue_status' => 'ready',
                'ready_at'     => now(),
            ]);
        }

        return response()->json([
            'data' => $this->transform($item->fresh(['transaction', 'product.category']))
        ]);
    }

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
            'product_image_url'        => $i->product?->image_url,
            'category' => $i->product?->category ? [
                'name'  => $i->product->category->name,
                'color' => $i->product->category->color,
            ] : null,
            'transaction' => $i->transaction ? [
                'id'             => $i->transaction->id,
                'invoice_number' => $i->transaction->invoice_number,
                'created_at'     => $i->transaction->created_at->toISOString(),
                'table_number'   => $i->transaction->table_number,
            ] : null,
        ];
    }
}