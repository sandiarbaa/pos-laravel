<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = StockMovement::with([
            'product:id,name',
            'user:id,name',
        ])
        ->where('business_id', $user->business_id)
        ->latest();

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $movements = $query
        ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => $movements->getCollection()->map(fn ($m) => [
                'id' => $m->id,
                'product_id' => $m->product_id,
                'product_name' => $m->product?->name,
                'action' => $m->action,
                'qty' => $m->qty,
                'note' => $m->note,
                'created_by' => [
                    'id' => $m->user?->id,
                    'name' => $m->user?->name,
                ],
                'created_at' => $m->created_at->toISOString(),
            ]),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'action'     => 'required|in:add,subtract',
            'qty'        => 'required|integer|min:1',
            'note'       => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();

        try {
            $user = $request->user();

            $product = Product::findOrFail($request->product_id);

            // Security: hanya boleh adjust produk milik business sendiri
            if ($product->business_id !== $user->business_id) {
                return response()->json([
                    'message' => 'Unauthorized.'
                ], 403);
            }

            $qty = (int) $request->qty;

            if ($request->action === 'subtract') {

                if ($product->stock < $qty) {
                    return response()->json([
                        'message' => 'Stok tidak mencukupi.'
                    ], 422);
                }

                $product->decrement('stock', $qty);
            } else {
                $product->increment('stock', $qty);
            }

            $movement = StockMovement::create([
                'business_id' => $product->business_id,
                'product_id'  => $product->id,
                'created_by'  => $user->id,
                'action'      => $request->action,
                'qty'         => $qty,
                'note'        => $request->note,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Stock adjustment berhasil.',
                'data' => [
                    'id'            => $movement->id,
                    'product_id'    => $product->id,
                    'product_name'  => $product->name,
                    'action'        => $movement->action,
                    'qty'           => $movement->qty,
                    'note'          => $movement->note,
                    'current_stock' => $product->fresh()->stock,
                    'created_at'    => $movement->created_at,
                ]
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Gagal melakukan stock adjustment.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
