<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionItem;
use Illuminate\Http\Request;

class KitchenController extends Controller
{
    public function queue(Request $request)
    {
        $businessId = $request->user()->business_id;

        $items = TransactionItem::with([
                'transaction:id,invoice_number,created_at,table_number,queue_color',
                'product:id,image,category_id',
                'product.category:id,name,color',
            ])
            ->whereHas('transaction', function ($q) use ($businessId) {
                $q->whereIn('status', ['paid', 'open_bill'])
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
        $tableNumber = $i->transaction?->table_number;
        $isTakeaway  = is_null($tableNumber) || $tableNumber === '0';

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
                'table_number'   => $tableNumber,
                'order_type'  => $isTakeaway ? 'takeaway' : 'dine_in',
                'queue_color' => $i->transaction->queue_color,
            ] : null,
        ];
    }

    public function toggleStock(Request $request, int $id)
    {
        $product    = \App\Models\Product::findOrFail($id);
        $businessId = $request->user()->business_id;

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
                'stock'           => $p->stock,
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

        if ($me->isAdmin()) {
            $businessIds = \App\Models\User::where('owner_id', $me->id)
                ->whereNotNull('business_id')
                ->pluck('business_id')
                ->unique()
                ->values();

            if ($businessIds->isEmpty()) {
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
            $businessIds = null;
        }

        $request->validate([
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date|after_or_equal:date_from',
            'product_name' => 'nullable|string|max:100',
            'category_id'  => 'nullable|integer|exists:categories,id',
            'source'       => 'nullable|in:dine-in,takeaway',
            'table_number' => 'nullable|string|max:20',
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);

        $dateFrom    = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo      = $request->input('date_to',   now()->toDateString());
        $productName = $request->input('product_name');
        $categoryId  = $request->input('category_id');
        $source      = $request->input('source');
        $tableNumber = $request->input('table_number');
        $perPage     = $request->input('per_page', 20);

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

        $aggRow = (clone $query)
            ->whereNotNull('cooking_duration_seconds')
            ->selectRaw('
                AVG(ABS(cooking_duration_seconds)) as avg_sec,
                MIN(ABS(cooking_duration_seconds)) as fastest_sec,
                MAX(ABS(cooking_duration_seconds)) as slowest_sec
            ')->first();

        $summary = [
            'total_items_done'        => (clone $query)->count(),
            'total_quantity'          => (clone $query)->sum('quantity'),
            'avg_cooking_seconds'     => (int) round($aggRow->avg_sec ?? 0),
            'fastest_cooking_seconds' => $aggRow->fastest_sec !== null ? (int) $aggRow->fastest_sec : null,
            'slowest_cooking_seconds' => $aggRow->slowest_sec !== null ? (int) $aggRow->slowest_sec : null,
        ];

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

    public function stockReport(Request $request)
    {
        $me = $request->user();

        // Resolve business_id(s)
        if ($me->isAdmin()) {
            $businessIds = \App\Models\User::where('owner_id', $me->id)
                ->whereNotNull('business_id')
                ->pluck('business_id')
                ->unique()
                ->values();
        } else {
            $businessIds = collect([$me->business_id])->filter();
        }

        $request->validate([
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date|after_or_equal:date_from',
            'product_id'  => 'nullable|integer|exists:products,id',
            'category_id' => 'nullable|integer|exists:categories,id',
        ]);

        $dateFrom   = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo     = $request->input('date_to',   now()->toDateString());
        $productId  = $request->input('product_id');
        $categoryId = $request->input('category_id');

        // ── 1. Total terjual per product_id dalam periode ──────────────────────
        $soldQuery = \App\Models\TransactionItem::query()
            ->selectRaw('product_id, SUM(quantity) as total_sold')
            ->whereNotNull('product_id')
            ->whereHas('transaction', function ($q) use ($businessIds, $dateFrom, $dateTo) {
                $q->where('status', 'paid')
                ->whereIn('business_id', $businessIds)
                ->whereBetween('created_at', [
                    $dateFrom . ' 00:00:00',
                    $dateTo   . ' 23:59:59',
                ]);
            })
            ->groupBy('product_id');

        $soldMap = $soldQuery->pluck('total_sold', 'product_id');

        // ── 2. Adjustments per product_id dalam periode ────────────────────────
        $adjustments = \App\Models\StockMovement::query()
            ->selectRaw("product_id, action, SUM(qty) as total_qty")
            ->whereIn('business_id', $businessIds)
            ->whereBetween('created_at', [
                $dateFrom . ' 00:00:00',
                $dateTo   . ' 23:59:59',
            ])
            ->when($productId, fn($q) => $q->where('product_id', $productId))
            ->groupBy('product_id', 'action')
            ->get();

        // Restructure: product_id => { add, subtract }
        $adjMap = [];
        foreach ($adjustments as $row) {
            $pid = $row->product_id;
            if (!isset($adjMap[$pid])) {
                $adjMap[$pid] = ['add' => 0, 'subtract' => 0];
            }
            $adjMap[$pid][$row->action] = (int) $row->total_qty;
        }

        // Notes per product_id
        $notes = \App\Models\StockMovement::query()
            ->whereIn('business_id', $businessIds)
            ->whereBetween('created_at', [
                $dateFrom . ' 00:00:00',
                $dateTo   . ' 23:59:59',
            ])
            ->when($productId, fn($q) => $q->where('product_id', $productId))
            ->whereNotNull('note')
            ->get(['product_id', 'note', 'action', 'qty', 'created_at']);

        $notesMap = $notes->groupBy('product_id')->map(fn($rows) => $rows->map(fn($r) => [
            'action'     => $r->action,
            'qty'        => $r->qty,
            'note'       => $r->note,
            'created_at' => $r->created_at->toISOString(),
        ])->values());

        // ── 3. Produk ───────────────────────────────────────────────────
        $perPage = $request->input('per_page', 20);

        $productsPaginated = \App\Models\Product::with('category')
            ->whereIn('business_id', $businessIds)
            ->when($productId,  fn($q) => $q->where('id', $productId))
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate($perPage);

        // ── 4. Build report ───────────────────────────────────────────────────
        $report = $productsPaginated->getCollection()->map(function ($p) use ($soldMap, $adjMap, $notesMap) {
            $totalSold     = (int) ($soldMap[$p->id] ?? 0);
            $adjAdd        = (int) ($adjMap[$p->id]['add']      ?? 0);
            $adjSubtract   = (int) ($adjMap[$p->id]['subtract'] ?? 0);
            $currentStock  = (int) $p->stock;
            $stockInitial  = $currentStock + $totalSold - $adjAdd + $adjSubtract;
            $stockTeoritis = $stockInitial - $totalSold;
            $selisih       = $currentStock - $stockTeoritis;
            $hasAdj        = isset($adjMap[$p->id]);

            if ($selisih === 0)            $flag = 'ok';
            elseif ($hasAdj)               $flag = 'explained';
            else                           $flag = 'unexplained';

            return [
                'product_id'              => $p->id,
                'product_name'            => $p->name,
                'category'                => $p->category ? [
                    'id'    => $p->category->id,
                    'name'  => $p->category->name,
                    'color' => $p->category->color,
                ] : null,
                'current_stock'           => $currentStock,
                'total_sold'              => $totalSold,
                'stock_initial_estimated' => $stockInitial,
                'adjustment' => [
                    'total_add'      => $adjAdd,
                    'total_subtract' => $adjSubtract,
                    'notes'          => $notesMap[$p->id] ?? [],
                ],
                'stock_teoritis_akhir' => $stockTeoritis,
                'selisih'              => $selisih,
                'flag'                 => $flag,
            ];
        });

        // ── 5. Summary (dari semua produk, bukan hanya halaman ini) ───────────
        $totalProducts   = \App\Models\Product::whereIn('business_id', $businessIds)
            ->when($productId,  fn($q) => $q->where('id', $productId))
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->where('is_active', true)
            ->count();

        // Summary flag dihitung dari semua data (bukan per page)
        // Kita pakai soldMap & adjMap yang sudah di-load semua
        $allProducts = \App\Models\Product::whereIn('business_id', $businessIds)
            ->when($productId,  fn($q) => $q->where('id', $productId))
            ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
            ->where('is_active', true)
            ->pluck('id');

        $summaryOk = 0; $summaryExplained = 0; $summaryUnexplained = 0;
        foreach ($allProducts as $pid) {
            $sold    = (int) ($soldMap[$pid] ?? 0);
            $add     = (int) ($adjMap[$pid]['add'] ?? 0);
            $sub     = (int) ($adjMap[$pid]['subtract'] ?? 0);
            $current = \App\Models\Product::find($pid)?->stock ?? 0;
            $initial = $current + $sold - $add + $sub;
            $teoritis = $initial - $sold;
            $selisih  = $current - $teoritis;
            $hasAdj   = isset($adjMap[$pid]);

            if ($selisih === 0)  $summaryOk++;
            elseif ($hasAdj)     $summaryExplained++;
            else                 $summaryUnexplained++;
        }

        return response()->json([
            'summary' => [
                'total_products'    => $totalProducts,
                'total_ok'          => $summaryOk,
                'total_explained'   => $summaryExplained,
                'total_unexplained' => $summaryUnexplained,
            ],
            'data' => $report->values(),
            'meta' => [
                'current_page' => $productsPaginated->currentPage(),
                'last_page'    => $productsPaginated->lastPage(),
                'per_page'     => $productsPaginated->perPage(),
                'total'        => $productsPaginated->total(),
            ],
            'period' => ['date_from' => $dateFrom, 'date_to' => $dateTo],
        ]);
    }
}
