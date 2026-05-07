<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionItem;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    public function queue(Request $request)
    {
        // business_id selalu dari user yang login — tidak bisa di-spoof via query param
        $businessId = $request->user()->business_id;

        $items = TransactionItem::with([
                'transaction:id,invoice_number,created_at,table_number',
                'product:id,image,category_id',
                'product.category:id,name,color',
            ])
            ->whereHas('transaction', function ($q) use ($businessId) {
                $q->where('status', 'paid')
                  ->where('business_id', $businessId);
            })
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
            'is_out_of_stock'          => $i->product?->is_out_of_stock ?? false,
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

    public function toggleStock(Request $request, int $id)
    {
        $product    = \App\Models\Product::findOrFail($id);
        $businessId = $request->user()->business_id;

        // pastikan produk milik bisnis yang sama dengan user yang login
        if ($product->business_id !== $businessId) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $product->update([
            'is_out_of_stock' => !$product->is_out_of_stock,
        ]);

        return response()->json([
            'data' => [
                'id'              => $product->id,
                'name'            => $product->name,
                'is_out_of_stock' => $product->is_out_of_stock,
            ]
        ]);
    }

    public function products(Request $request)
    {
        // business_id selalu dari user yang login
        $businessId = $request->user()->business_id;

        $products = \App\Models\Product::with('category')
            ->where('is_active', true)
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id'              => $p->id,
                'name'            => $p->name,
                'image_url'       => $p->image_url,
                'is_out_of_stock' => $p->is_out_of_stock,
                'category'        => $p->category ? [
                    'name'  => $p->category->name,
                    'color' => $p->category->color,
                ] : null,
            ]);

        return response()->json(['data' => $products]);
    }

    public function report(Request $request)
    {
        $me = $request->user();
    
        // ── Resolve business_id(s) berdasarkan role
        // - kasir   → punya business_id langsung di kolom user
        // - admin   → tidak punya business_id sendiri, tapi punya kasir2
        //             yang masing2 punya business_id
        // - superadmin → akses semua (tidak difilter bisnis)
        if ($me->isAdmin()) {
            // Ambil semua business_id dari kasir yang dimiliki owner ini
            $businessIds = \App\Models\User::where('owner_id', $me->id)
                ->whereNotNull('business_id')
                ->pluck('business_id')
                ->unique()
                ->values();
    
            if ($businessIds->isEmpty()) {
                // Owner belum punya kasir — return kosong
                return response()->json([
                    'summary'      => [
                        'total_items_done'        => 0,
                        'total_quantity'          => 0,
                        'avg_cooking_seconds'     => 0,
                        'fastest_cooking_seconds' => null,
                        'slowest_cooking_seconds' => null,
                    ],
                    'best_sellers' => [],
                    'data'         => [
                        'data'         => [],
                        'current_page' => 1,
                        'last_page'    => 1,
                        'total'        => 0,
                        'per_page'     => 20,
                    ],
                ]);
            }
        } elseif ($me->isKasir()) {
            $businessIds = collect([$me->business_id])->filter();
        } else {
            // superadmin: tidak filter bisnis sama sekali
            $businessIds = null;
        }
    
        // --- Validasi input ---
        $request->validate([
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date|after_or_equal:date_from',
            'product_name'=> 'nullable|string|max:100',
            'category_id' => 'nullable|integer|exists:categories,id',
            'source'      => 'nullable|in:dine-in,takeaway',
            'table_number'=> 'nullable|string|max:20',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);
    
        $dateFrom    = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo      = $request->input('date_to',   now()->toDateString());
        $productName = $request->input('product_name');
        $categoryId  = $request->input('category_id');
        $source      = $request->input('source');
        $tableNumber = $request->input('table_number');
        $perPage     = $request->input('per_page', 20);
    
        // --- Base query: hanya item yang sudah DONE ---
        $query = TransactionItem::with([
                'transaction:id,invoice_number,created_at,table_number',
                'product:id,category_id',
                'product.category:id,name,color',
            ])
            ->whereHas('transaction', function ($q) use ($businessIds, $dateFrom, $dateTo, $source, $tableNumber) {
                $q->where('status', 'paid')
                ->whereBetween('created_at', [
                    $dateFrom . ' 00:00:00',
                    $dateTo   . ' 23:59:59',
                ]);
    
                // Filter bisnis — null berarti superadmin (akses semua)
                if ($businessIds !== null) {
                    $q->whereIn('business_id', $businessIds);
                }
                if ($source) {
                    $q->where('source', $source);
                }
                if ($tableNumber) {
                    $q->where('table_number', 'like', "%{$tableNumber}%");
                }
            })
            ->where('kitchen_status', 'done');
    
        if ($productName) {
            $query->where('product_name', 'like', "%{$productName}%");
        }
    
        if ($categoryId) {
            $query->whereHas('product', fn($q) => $q->where('category_id', $categoryId));
        }
    
        // --- Summary / agregat ---
        // ABS() di SQL supaya MIN/MAX dihitung dari nilai positif
        // (fix bug timezone mismatch yang bikin cooking_duration_seconds negatif)
        $aggRow = (clone $query)
            ->whereNotNull('cooking_duration_seconds')
            ->selectRaw('
                AVG(ABS(cooking_duration_seconds)) as avg_sec,
                MIN(ABS(cooking_duration_seconds)) as fastest_sec,
                MAX(ABS(cooking_duration_seconds)) as slowest_sec
            ')->first();
    
        $summary = [
            'total_items_done'       => (clone $query)->count(),
            'total_quantity'         => (clone $query)->sum('quantity'),
            'avg_cooking_seconds'    => (int) round($aggRow->avg_sec ?? 0),
            'fastest_cooking_seconds'=> $aggRow->fastest_sec !== null ? (int) $aggRow->fastest_sec : null,
            'slowest_cooking_seconds'=> $aggRow->slowest_sec !== null ? (int) $aggRow->slowest_sec : null,
        ];
    
        // --- Best seller (top 10 menu berdasarkan quantity) ---
        $bestSellers = TransactionItem::selectRaw('
                    product_name,
                    SUM(quantity) as total_qty,
                    COUNT(*) as total_orders,
                    AVG(ABS(cooking_duration_seconds)) as avg_cooking_seconds,
                    MIN(ABS(cooking_duration_seconds)) as min_cooking_seconds,
                    MAX(ABS(cooking_duration_seconds)) as max_cooking_seconds
                ')
            ->whereHas('transaction', function ($q) use ($businessIds, $dateFrom, $dateTo) {
                $q->where('status', 'paid')
                ->whereBetween('created_at', [
                    $dateFrom . ' 00:00:00',
                    $dateTo   . ' 23:59:59',
                ]);
    
                if ($businessIds !== null) {
                    $q->whereIn('business_id', $businessIds);
                }
            })
            ->where('kitchen_status', 'done')
            ->when($categoryId, fn($q) => $q->whereHas('product', fn($q2) => $q2->where('category_id', $categoryId)))
            ->groupBy('product_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'product_name'        => $row->product_name,
                'total_qty'           => (int) $row->total_qty,
                'total_orders'        => (int) $row->total_orders,
                'avg_cooking_seconds' => abs((int) round($row->avg_cooking_seconds ?? 0)),
                'min_cooking_seconds' => $row->min_cooking_seconds !== null ? abs((int) $row->min_cooking_seconds) : null,
                'max_cooking_seconds' => $row->max_cooking_seconds !== null ? abs((int) $row->max_cooking_seconds) : null,
            ]);
    
        // --- List detail (paginated) ---
        $items = $query
            ->orderBy('cooking_done_at', 'desc')
            ->paginate($perPage)
            ->through(fn($i) => [
                'id'                       => $i->id,
                'product_name'             => $i->product_name,
                'product_sku'              => $i->product_sku,
                'quantity'                 => $i->quantity,
                'source'                   => $i->source,
                'kitchen_status'           => $i->kitchen_status,
                'cooking_started_at'       => $i->cooking_started_at?->toISOString(),
                'cooking_done_at'          => $i->cooking_done_at?->toISOString(),
                'cooking_duration_seconds' => $i->cooking_duration_seconds !== null ? abs((int) $i->cooking_duration_seconds) : null,
                'pause_duration_seconds'   => abs((int) ($i->pause_duration_seconds ?? 0)),
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
            ]);
    
        return response()->json([
            'summary'      => $summary,
            'best_sellers' => $bestSellers,
            'data'         => $items,
        ]);
    }
}
