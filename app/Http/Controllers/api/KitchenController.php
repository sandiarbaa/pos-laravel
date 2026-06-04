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

        // ── Base query
        $query = TransactionItem::with([
                'transaction:id,invoice_number,created_at,table_number',
                'product:id,category_id',
                'product.category:id,name,color',
            ])
            ->whereHas('transaction', function ($q) use ($businessIds, $tableNumber) {
                // FIX: hapus date & source dari sini
                $q->where('status', 'paid');

                if ($businessIds !== null) {
                    $q->whereIn('business_id', $businessIds);
                }
                if ($tableNumber) {
                    $q->where('table_number', 'like', "%{$tableNumber}%");
                }
            })
            ->where('kitchen_status', 'done')
            // FIX: filter date by cooking_done_at di level item, bukan transaction.created_at
            ->whereBetween('cooking_done_at', [
                $dateFrom . ' 00:00:00',
                $dateTo   . ' 23:59:59',
            ]);

        if ($source) {
            $query->whereHas('transaction', function ($q) use ($source) {
                if ($source === 'dine-in') {
                    $q->whereNotNull('table_number')
                    ->where('table_number', '!=', '0')
                    ->where('table_number', '!=', '');
                } else {
                    // takeaway
                    $q->where(function ($q2) {
                        $q2->whereNull('table_number')
                        ->orWhere('table_number', '0')
                        ->orWhere('table_number', '');
                    });
                }
            });
        }

        if ($productName) {
            $query->where('product_name', 'like', "%{$productName}%");
        }

        if ($categoryId) {
            $query->whereHas('product', fn($q) => $q->where('category_id', $categoryId));
        }

        // ── Aggregasi summary
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

        // ── Best sellers (ikut filter date & business, tapi ga ikut filter source/product/category)
        $bestSellers = TransactionItem::selectRaw('
                product_name,
                SUM(quantity) as total_qty,
                COUNT(*) as total_orders,
                AVG(ABS(cooking_duration_seconds)) as avg_cooking_seconds,
                MIN(ABS(cooking_duration_seconds)) as min_cooking_seconds,
                MAX(ABS(cooking_duration_seconds)) as max_cooking_seconds
            ')
            ->whereHas('transaction', function ($q) use ($businessIds) {
                $q->where('status', 'paid');
                if ($businessIds !== null) {
                    $q->whereIn('business_id', $businessIds);
                }
            })
            ->where('kitchen_status', 'done')
            ->whereBetween('cooking_done_at', [
                $dateFrom . ' 00:00:00',
                $dateTo   . ' 23:59:59',
            ])
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

        // ── Paginated items
        $items = $query
            ->orderBy('cooking_done_at', 'desc')
            ->paginate($perPage)
            ->through(fn($i) => [
                'id'                       => $i->id,
                'product_name'             => $i->product_name,
                'product_sku'              => $i->product_sku,
                'quantity'                 => $i->quantity,
                'source'                   => (
                                                $i->transaction &&
                                                $i->transaction->table_number &&
                                                $i->transaction->table_number !== '0' &&
                                                $i->transaction->table_number !== ''
                                            ) ? 'dine-in' : 'takeaway',
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
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date|after_or_equal:date_from',
            'product_id'   => 'nullable|integer|exists:products,id',
            'category_id'  => 'nullable|integer|exists:categories,id',
            'product_name' => 'nullable|string|max:100',
            'flag'         => 'nullable|in:ok,explained,unexplained', // ← BARU
            'per_page'     => 'nullable|integer|min:1|max:100',
        ]);

        $dateFrom    = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo      = $request->input('date_to',   now()->toDateString());
        $productId   = $request->input('product_id');
        $categoryId  = $request->input('category_id');
        $productName = $request->input('product_name');
        $flagFilter  = $request->input('flag'); // ← BARU
        $perPage     = $request->input('per_page', 20);
        $exportExcel = $request->boolean('export'); // ← BARU: ?export=1

        // ── 1. Total terjual
        $soldMap = \App\Models\TransactionItem::selectRaw('product_id, SUM(quantity) as total_sold')
            ->whereNotNull('product_id')
            ->whereHas('transaction', function ($q) use ($businessIds, $dateFrom, $dateTo) {
                $q->where('status', 'paid')
                  ->whereIn('business_id', $businessIds)
                  ->whereBetween('created_at', [
                      $dateFrom . ' 00:00:00',
                      $dateTo   . ' 23:59:59',
                  ]);
            })
            ->groupBy('product_id')
            ->pluck('total_sold', 'product_id');

        // ── 2. Adjustments
        $adjustments = \App\Models\StockMovement::selectRaw('product_id, action, SUM(qty) as total_qty')
            ->whereIn('business_id', $businessIds)
            ->whereBetween('created_at', [
                $dateFrom . ' 00:00:00',
                $dateTo   . ' 23:59:59',
            ])
            ->when($productId, fn($q) => $q->where('product_id', $productId))
            ->groupBy('product_id', 'action')
            ->get();

        $adjMap = [];
        foreach ($adjustments as $row) {
            $pid = $row->product_id;
            if (!isset($adjMap[$pid])) $adjMap[$pid] = ['add' => 0, 'subtract' => 0];
            $adjMap[$pid][$row->action] = (int) $row->total_qty;
        }

        // Notes
        $notesMap = \App\Models\StockMovement::query()
            ->whereIn('business_id', $businessIds)
            ->whereBetween('created_at', [
                $dateFrom . ' 00:00:00',
                $dateTo   . ' 23:59:59',
            ])
            ->when($productId, fn($q) => $q->where('product_id', $productId))
            ->whereNotNull('note')
            ->get(['product_id', 'note', 'action', 'qty', 'created_at'])
            ->groupBy('product_id')
            ->map(fn($rows) => $rows->map(fn($r) => [
                'action'     => $r->action,
                'qty'        => $r->qty,
                'note'       => $r->note,
                'created_at' => $r->created_at->toISOString(),
            ])->values());

        // ── Helper: build satu item report
        $buildItem = function ($p) use ($soldMap, $adjMap, $notesMap) {
            $totalSold     = (int) ($soldMap[$p->id] ?? 0);
            $adjAdd        = (int) ($adjMap[$p->id]['add']      ?? 0);
            $adjSubtract   = (int) ($adjMap[$p->id]['subtract'] ?? 0);
            $currentStock  = (int) $p->stock;
            $stockInitial  = $currentStock + $totalSold - $adjAdd + $adjSubtract;
            $stockTeoritis = $stockInitial - $totalSold;
            $selisih       = $currentStock - $stockTeoritis;
            $hasAdj        = isset($adjMap[$p->id]);

            if ($selisih === 0)   $flag = 'ok';
            elseif ($hasAdj)      $flag = 'explained';
            else                  $flag = 'unexplained';

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
        };

        // ── Base product query (shared)
        $baseQuery = \App\Models\Product::with('category')
            ->whereIn('business_id', $businessIds)
            ->when($productId,   fn($q) => $q->where('id', $productId))
            ->when($categoryId,  fn($q) => $q->where('category_id', $categoryId))
            ->when($productName, fn($q) => $q->where('name', 'like', "%{$productName}%"))
            ->where('is_active', true)
            ->orderBy('name');

        // ── Summary (semua produk, tanpa filter flag)
        $allProductsData = (clone $baseQuery)->pluck('stock', 'id');
        $summaryOk = 0; $summaryExplained = 0; $summaryUnexplained = 0;
        foreach ($allProductsData as $pid => $currentStockVal) {
            $sold     = (int) ($soldMap[$pid] ?? 0);
            $add      = (int) ($adjMap[$pid]['add']      ?? 0);
            $sub      = (int) ($adjMap[$pid]['subtract'] ?? 0);
            $initial  = $currentStockVal + $sold - $add + $sub;
            $teoritis = $initial - $sold;
            $selisih  = $currentStockVal - $teoritis;
            $hasAdj   = isset($adjMap[$pid]);

            if ($selisih === 0)   $summaryOk++;
            elseif ($hasAdj)      $summaryExplained++;
            else                  $summaryUnexplained++;
        }

        $summary = [
            'total_products'    => $allProductsData->count(),
            'total_ok'          => $summaryOk,
            'total_explained'   => $summaryExplained,
            'total_unexplained' => $summaryUnexplained,
        ];

        // ── Export Excel
        if ($exportExcel) {
            $allProducts = (clone $baseQuery)->get();
            $rows = $allProducts->map(fn($p) => $buildItem($p));

            // Filter flag jika ada
            if ($flagFilter) {
                $rows = $rows->filter(fn($r) => $r['flag'] === $flagFilter)->values();
            }

            return $this->exportStockReportExcel($rows, $dateFrom, $dateTo, $summary);
        }

        // ── Paginated response
        $productsPaginated = (clone $baseQuery)->paginate($perPage);

        $report = $productsPaginated->getCollection()->map(fn($p) => $buildItem($p));

        // Filter flag setelah build (karena flag dihitung dari data, bukan kolom DB)
        if ($flagFilter) {
            $report = $report->filter(fn($item) => $item['flag'] === $flagFilter)->values();
        }

        return response()->json([
            'summary' => $summary,
            'data'    => $report->values(),
            'meta'    => [
                'current_page' => $productsPaginated->currentPage(),
                'last_page'    => $productsPaginated->lastPage(),
                'per_page'     => $productsPaginated->perPage(),
                'total'        => $productsPaginated->total(),
            ],
            'period' => ['date_from' => $dateFrom, 'date_to' => $dateTo],
        ]);
    }

    // ── Excel export (native, tanpa library)
    private function exportStockReportExcel($rows, string $dateFrom, string $dateTo, array $summary)
    {
        $flagLabel = ['ok' => 'Sesuai', 'explained' => 'Ada Catatan', 'unexplained' => 'Tidak Sesuai'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Stok');

        // ── Title & Summary
        $sheet->setCellValue('A1', 'Laporan Stok');
        $sheet->setCellValue('A2', 'Periode');
        $sheet->setCellValue('B2', $dateFrom . ' s/d ' . $dateTo);
        $sheet->setCellValue('A3', 'Total Produk');
        $sheet->setCellValue('B3', $summary['total_products']);
        $sheet->setCellValue('A4', 'Sesuai');
        $sheet->setCellValue('B4', $summary['total_ok']);
        $sheet->setCellValue('A5', 'Ada Catatan');
        $sheet->setCellValue('B5', $summary['total_explained']);
        $sheet->setCellValue('A6', 'Tidak Sesuai');
        $sheet->setCellValue('B6', $summary['total_unexplained']);

        // Style title
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // ── Header tabel (baris 8)
        $headers = ['No', 'Produk', 'Kategori', 'Stok Awal', 'Terjual', 'Adj+', 'Adj-', 'Teoritis', 'Aktual', 'Selisih', 'Status', 'Catatan Adjustment'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '8', $header);
            $col++;
        }

        // Style header
        $sheet->getStyle('A8:L8')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // ── Data rows mulai dari baris 9
        $rowNum = 9;
        foreach ($rows as $i => $r) {
            $notes = collect($r['adjustment']['notes'])
                ->map(fn($n) => '[' . strtoupper($n['action']) . ' ' . $n['qty'] . '] ' . $n['note'])
                ->implode(' | ');

            $flagColor = match($r['flag']) {
                'ok'          => 'C6EFCE', // hijau
                'explained'   => 'FFEB9C', // kuning
                'unexplained' => 'FFC7CE', // merah
                default       => 'FFFFFF',
            };

            $rowData = [
                $i + 1,
                $r['product_name'],
                $r['category']['name'] ?? '-',
                $r['stock_initial_estimated'],
                $r['total_sold'],
                $r['adjustment']['total_add'],
                $r['adjustment']['total_subtract'],
                $r['stock_teoritis_akhir'],
                $r['current_stock'],
                $r['selisih'],
                $flagLabel[$r['flag']] ?? $r['flag'],
                $notes,
            ];

            $col = 'A';
            foreach ($rowData as $value) {
                $sheet->setCellValue($col . $rowNum, $value);
                $col++;
            }

            // Border semua kolom
            $sheet->getStyle('A' . $rowNum . ':L' . $rowNum)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);

            // Warna kolom Status (K)
            $sheet->getStyle('K' . $rowNum)->applyFromArray([
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $flagColor],
                ],
            ]);

            $rowNum++;
        }

        // ── Auto width semua kolom
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Output
        $filename = 'laporan-stok-' . $dateFrom . '-sd-' . $dateTo . '.xlsx';

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function stockReportExport(Request $request)
    {
        // Auth manual via ?token=xxx (sama seperti TransactionController@export)
        $token = $request->query('token');
        $user  = \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->tokenable;

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Set user ke request supaya logic di bawah jalan
        $request->setUserResolver(fn() => $user);

        // Panggil logic export yang sudah ada di stockReport
        return $this->stockReport($request->merge(['export' => '1']));
    }

    public function reportExport(Request $request)
    {
        // Auth manual via ?token=xxx (sama seperti stockReportExport)
        $token = $request->query('token');
        $user  = \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->tokenable;

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->setUserResolver(fn() => $user);

        // ── Resolve business IDs (duplikat logic dari report())
        $me = $user;

        if ($me->isAdmin()) {
            $businessIds = \App\Models\User::where('owner_id', $me->id)
                ->whereNotNull('business_id')
                ->pluck('business_id')
                ->unique()
                ->values();
        } elseif ($me->isKasir()) {
            $businessIds = collect([$me->business_id])->filter();
        } else {
            $businessIds = null;
        }

        $request->validate([
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date|after_or_equal:date_from',
            'product_name' => 'nullable|string|max:100',
            'source'       => 'nullable|in:dine-in,takeaway',
        ]);

        $dateFrom    = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo      = $request->input('date_to',   now()->toDateString());
        $productName = $request->input('product_name');
        $source      = $request->input('source');

        // ── Base query (sama persis dengan report(), tanpa paginate)
        $query = \App\Models\TransactionItem::with([
                'transaction:id,invoice_number,created_at,table_number',
                'product:id,category_id',
                'product.category:id,name,color',
            ])
            ->whereHas('transaction', function ($q) use ($businessIds) {
                $q->where('status', 'paid');
                if ($businessIds !== null) {
                    $q->whereIn('business_id', $businessIds);
                }
            })
            ->where('kitchen_status', 'done')
            ->whereBetween('cooking_done_at', [
                $dateFrom . ' 00:00:00',
                $dateTo   . ' 23:59:59',
            ]);

        if ($source) {
            $query->whereHas('transaction', function ($q) use ($source) {
                if ($source === 'dine-in') {
                    $q->whereNotNull('table_number')
                      ->where('table_number', '!=', '0')
                      ->where('table_number', '!=', '');
                } else {
                    $q->where(function ($q2) {
                        $q2->whereNull('table_number')
                           ->orWhere('table_number', '0')
                           ->orWhere('table_number', '');
                    });
                }
            });
        }

        if ($productName) {
            $query->where('product_name', 'like', "%{$productName}%");
        }

        // ── Summary untuk header sheet
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

        // ── Best sellers untuk sheet kedua
        $bestSellers = \App\Models\TransactionItem::selectRaw('
                product_name,
                SUM(quantity) as total_qty,
                COUNT(*) as total_orders,
                AVG(ABS(cooking_duration_seconds)) as avg_cooking_seconds,
                MIN(ABS(cooking_duration_seconds)) as min_cooking_seconds,
                MAX(ABS(cooking_duration_seconds)) as max_cooking_seconds
            ')
            ->whereHas('transaction', function ($q) use ($businessIds) {
                $q->where('status', 'paid');
                if ($businessIds !== null) {
                    $q->whereIn('business_id', $businessIds);
                }
            })
            ->where('kitchen_status', 'done')
            ->whereBetween('cooking_done_at', [
                $dateFrom . ' 00:00:00',
                $dateTo   . ' 23:59:59',
            ])
            ->groupBy('product_name')
            ->orderByDesc('total_qty')
            ->limit(20)
            ->get();

        // ── Semua items (tanpa paginate)
        $items = $query->orderBy('cooking_done_at', 'desc')->get();

        return $this->exportKitchenReportExcel($items, $bestSellers, $summary, $dateFrom, $dateTo);
    }

    private function exportKitchenReportExcel(
        $items,
        $bestSellers,
        array $summary,
        string $dateFrom,
        string $dateTo
    ) {
        $fmtDuration = function ($seconds): string {
            if ($seconds === null) return '—';
            $s = (int) abs($seconds);
            $m = intdiv($s, 60);
            $r = $s % 60;
            if ($m === 0) return "{$r}d";
            return "{$m}m {$r}d";
        };

        $fmtDateTime = function (?string $iso) use ($fmtDuration): string {
            if (!$iso) return '—';
            try {
                $d = new \DateTime($iso);
                $d->setTimezone(new \DateTimeZone(config('app.timezone', 'Asia/Jakarta')));
                return $d->format('d/m/Y H:i');
            } catch (\Throwable) {
                return $iso;
            }
        };

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // ══════════════════════════════════════════
        //  SHEET 1 — Detail Transaksi
        // ══════════════════════════════════════════
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Detail Transaksi');

        // ── Info & Summary (baris 1-7)
        $sheet1->setCellValue('A1', 'Laporan Dapur — Detail Transaksi');
        $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet1->setCellValue('A2', 'Periode');
        $sheet1->setCellValue('B2', $dateFrom . ' s/d ' . $dateTo);

        $sheet1->setCellValue('A3', 'Item Selesai');
        $sheet1->setCellValue('B3', $summary['total_items_done']);

        $sheet1->setCellValue('A4', 'Total Porsi');
        $sheet1->setCellValue('B4', $summary['total_quantity']);

        $sheet1->setCellValue('A5', 'Rata-rata Masak');
        $sheet1->setCellValue('B5', $fmtDuration($summary['avg_cooking_seconds']));

        $sheet1->setCellValue('A6', 'Tercepat');
        $sheet1->setCellValue('B6', $fmtDuration($summary['fastest_cooking_seconds']));

        $sheet1->setCellValue('A7', 'Terlama');
        $sheet1->setCellValue('B7', $fmtDuration($summary['slowest_cooking_seconds']));

        // ── Header tabel (baris 9)
        $headers1 = [
            'No', 'Menu', 'SKU', 'Kategori', 'Qty', 'Tipe',
            'No. Meja', 'Invoice', 'Selesai Masak', 'Durasi Masak', 'Jeda',
        ];
        $col = 'A';
        foreach ($headers1 as $h) {
            $sheet1->setCellValue($col . '9', $h);
            $col++;
        }

        $sheet1->getStyle('A9:K9')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ]);

        // ── Data rows mulai baris 10
        $rowNum = 10;
        foreach ($items as $idx => $i) {
            $trx        = $i->transaction;
            $cat        = $i->product?->category;
            $durSec     = $i->cooking_duration_seconds !== null ? abs((int) $i->cooking_duration_seconds) : null;
            $pauseSec   = abs((int) ($i->pause_duration_seconds ?? 0));
            $isTakeaway = !$trx?->table_number
                          || $trx->table_number === '0'
                          || $trx->table_number === '';
            $source     = $isTakeaway ? 'Takeaway' : 'Dine-in';
            $isLong     = $durSec !== null && $durSec > 900;

            $row = [
                $idx + 1,
                $i->product_name,
                $i->product_sku ?? '',
                $cat?->name ?? '—',
                $i->quantity,
                $source,
                $isTakeaway ? '—' : ('Meja ' . $trx->table_number),
                $trx?->invoice_number ?? '—',
                $fmtDateTime($i->cooking_done_at?->toISOString()),
                $fmtDuration($durSec),
                $pauseSec > 0 ? $fmtDuration($pauseSec) : '—',
            ];

            $col = 'A';
            foreach ($row as $val) {
                $sheet1->setCellValue($col . $rowNum, $val);
                $col++;
            }

            // Border semua kolom
            $sheet1->getStyle('A' . $rowNum . ':K' . $rowNum)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color'       => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ]);

            // Highlight durasi lama (merah muda)
            if ($isLong) {
                $sheet1->getStyle('J' . $rowNum)->applyFromArray([
                    'fill' => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FFC7CE'],
                    ],
                    'font' => ['bold' => true, 'color' => ['rgb' => 'C00000']],
                ]);
            }

            // Zebra stripe baris genap
            if ($idx % 2 === 1) {
                $sheet1->getStyle('A' . $rowNum . ':K' . $rowNum)->applyFromArray([
                    'fill' => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ],
                ]);
            }

            $rowNum++;
        }

        // Auto width sheet 1
        foreach (range('A', 'K') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        // ══════════════════════════════════════════
        //  SHEET 2 — Menu Terlaris
        // ══════════════════════════════════════════
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Menu Terlaris');

        $sheet2->setCellValue('A1', 'Laporan Dapur — Menu Terlaris');
        $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet2->setCellValue('A2', 'Periode');
        $sheet2->setCellValue('B2', $dateFrom . ' s/d ' . $dateTo);

        $headers2 = ['#', 'Menu', 'Total Porsi', 'Total Order', 'Avg Masak', 'Tercepat', 'Terlama'];
        $col = 'A';
        foreach ($headers2 as $h) {
            $sheet2->setCellValue($col . '4', $h);
            $col++;
        }

        $sheet2->getStyle('A4:G4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color'       => ['rgb' => '000000'],
                ],
            ],
        ]);

        $rowNum = 5;
        $medals = ['🥇', '🥈', '🥉'];
        foreach ($bestSellers as $idx => $bs) {
            $rank = $medals[$idx] ?? ('#' . ($idx + 1));
            $row  = [
                $rank,
                $bs->product_name,
                (int) $bs->total_qty,
                (int) $bs->total_orders,
                $fmtDuration($bs->avg_cooking_seconds),
                $fmtDuration($bs->min_cooking_seconds),
                $fmtDuration($bs->max_cooking_seconds),
            ];

            $col = 'A';
            foreach ($row as $val) {
                $sheet2->setCellValue($col . $rowNum, $val);
                $col++;
            }

            $sheet2->getStyle('A' . $rowNum . ':G' . $rowNum)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color'       => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ]);

            // Top 3 highlight
            if ($idx < 3) {
                $goldColors = ['FFF2CC', 'F2F2F2', 'FCE4D6'];
                $sheet2->getStyle('A' . $rowNum . ':G' . $rowNum)->applyFromArray([
                    'fill' => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => $goldColors[$idx]],
                    ],
                ]);
            } elseif ($idx % 2 === 1) {
                $sheet2->getStyle('A' . $rowNum . ':G' . $rowNum)->applyFromArray([
                    'fill' => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ],
                ]);
            }

            $rowNum++;
        }

        foreach (range('A', 'G') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Set sheet aktif ke sheet 1
        $spreadsheet->setActiveSheetIndex(0);

        // ── Output
        $filename = 'laporan-dapur-' . $dateFrom . '-sd-' . $dateTo . '.xlsx';
        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
