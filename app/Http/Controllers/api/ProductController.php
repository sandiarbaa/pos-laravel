<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // Helper: format satu product jadi array response
    private function formatProduct(Product $product): array
    {
        $business = $product->business;

        // Ambil pajak aktif dari relasi yang sudah di-eager load
        $activeTaxes = $business
            ? $business->taxes->where('is_active', true)->values()
            : collect();

        $totalTaxRate = $activeTaxes->sum('rate');
        $basePrice    = $product->discounted_price > 0 ? $product->discounted_price : $product->price;
        $finalPrice   = $totalTaxRate > 0
            ? (int) round($basePrice * (1 + $totalTaxRate / 100))
            : $basePrice;

        return [
            'id'               => $product->id,
            'business_id'      => $product->business_id,
            'name'             => $product->name,
            'description'      => $product->description,
            'sku'              => $product->sku,
            'price'            => $product->price,
            'discount_percent' => (float) $product->discount_percent,
            'discounted_price' => $product->discounted_price,
            'final_price'      => $finalPrice,
            'stock'            => $product->stock,
            'is_active'        => $product->is_active,
            'image_url'        => $product->image_url,
            'taxes'            => $activeTaxes->map(fn($tax) => [
                'id'   => $tax->id,
                'name' => $tax->name,
                'rate' => (float) $tax->rate,
            ])->values(),
            'business' => $business ? [
                'id'             => $business->id,
                'name'           => $business->name,
                'logo_url'       => $business->logo_url,
                'address'        => $business->address,
                'phone'          => $business->phone,
                'city'           => $business->city,
                'qris_image_url' => $business->qris_image_url,
            ] : null,
        ];
    }

    public function index(Request $request)
    {
        $user  = $request->user();

        // Eager load business beserta semua taxesnya sekaligus
        $query = Product::with(['business.taxes'])->where('is_active', true);

        if ($user && $user->isAdmin()) {
            $bizIds = Business::where('owner_id', $user->id)->pluck('id');
            $query->whereIn('business_id', $bizIds);
        }

        if ($request->filled('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        $sortBy  = $request->get('sort_by', 'name');
        $sortDir = $request->get('sort_dir', 'asc');
        $query->orderBy($sortBy, $sortDir);

        $perPage  = $request->get('per_page', 20);
        $products = $query->paginate($perPage);

        $products->getCollection()->transform(
            fn($product) => $this->formatProduct($product)
        );

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
        $product->load('business.taxes');

        return response()->json([
            'message' => 'Produk berhasil dibuat.',
            'data'    => $this->formatProduct($product),
        ], 201);
    }

    public function show(Product $product)
    {
        $product->load('business.taxes');
        return response()->json(['data' => $this->formatProduct($product)]);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'business_id'      => 'sometimes|exists:businesses,id',
            'name'             => 'sometimes|string|max:255',
            'description'      => 'nullable|string',
            'sku'              => 'nullable|string|unique:products,sku,' . $product->id,
            'price'            => 'sometimes|integer|min:0',
            'stock'            => 'sometimes|integer|min:0',
            'image'            => 'nullable|image|max:2048',
            'is_active'        => 'sometimes|boolean',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $data = $request->only([
            'business_id', 'name', 'description', 'sku',
            'price', 'stock', 'is_active', 'discount_percent',
        ]);

        $price   = $data['price'] ?? $product->price;
        $discPct = $data['discount_percent'] ?? $product->discount_percent;
        $data['discounted_price'] = $discPct > 0
            ? (int) round($price * (1 - $discPct / 100))
            : $price;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);
        $product->load('business.taxes');

        return response()->json([
            'message' => 'Produk berhasil diupdate.',
            'data'    => $this->formatProduct($product),
        ]);
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);

        return response()->json(['message' => 'Produk berhasil dinonaktifkan.']);
    }
}
