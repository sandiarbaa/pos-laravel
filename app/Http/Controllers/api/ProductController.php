<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('business')->where('is_active', true);

        // Filter by business
        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        // Search by name or sku
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Sort
        $sortBy  = $request->get('sort_by', 'name');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $products = $query->paginate($request->get('per_page', 20));

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'sku'         => 'nullable|string|unique:products,sku',
            'price'       => 'required|integer|min:0',
            'stock'       => 'required|integer|min:0',
            'image'       => 'nullable|image|max:2048',
        ]);

        $data = $request->only(['business_id', 'name', 'description', 'sku', 'price', 'stock']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);
        $product->load('business');

        return response()->json([
            'message' => 'Produk berhasil dibuat.',
            'data'    => $product,
        ], 201);
    }

    public function show(Product $product)
    {
        $product->load('business');
        return response()->json(['data' => $product]);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'business_id' => 'sometimes|exists:businesses,id',
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'sku'         => 'nullable|string|unique:products,sku,' . $product->id,
            'price'       => 'sometimes|integer|min:0',
            'stock'       => 'sometimes|integer|min:0',
            'image'       => 'nullable|image|max:2048',
            'is_active'   => 'sometimes|boolean',
        ]);

        $data = $request->only(['business_id', 'name', 'description', 'sku', 'price', 'stock', 'is_active']);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);
        $product->load('business');

        return response()->json([
            'message' => 'Produk berhasil diupdate.',
            'data'    => $product,
        ]);
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);

        return response()->json([
            'message' => 'Produk berhasil dinonaktifkan.',
        ]);
    }
}
