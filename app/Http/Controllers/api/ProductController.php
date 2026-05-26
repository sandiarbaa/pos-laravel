<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ProductController extends Controller
{
    private function formatProduct(Product $product): array
    {
        $business = $product->business;

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
            'category'         => $product->category ? [
                'id'    => $product->category->id,
                'name'  => $product->category->name,
                'color' => $product->category->color,
                'icon'  => $product->category->icon,
            ] : null,
            'name'             => $product->name,
            'description'      => $product->description,
            'sku'              => $product->sku,
            'price'            => $product->price,
            'discount_percent' => (float) $product->discount_percent,
            'discounted_price' => $product->discounted_price,
            'final_price'      => $finalPrice,
            'stock'            => $product->stock,
            'is_out_of_stock'  => (bool) $product->is_out_of_stock,
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
                'table_count'    => $business->table_count ?? 0,
            ] : null,

            // ── Options (varian) ─────────────────────────────────────────
            'option_groups' => $product->optionGroups->map(fn($group) => [
                'id'      => $group->id,
                'name'    => $group->name,
                'choices' => $group->choices->map(fn($c) => [
                    'id'    => $c->id,
                    'label' => $c->label,
                ])->values(),
            ])->values(),
        ];
    }

    public function index(Request $request)
    {
        $user  = $request->user();

        $query = Product::with([
            'business.taxes',
            'category',
            'optionGroups.choices', // ← tambah eager load
        ])->where('is_active', true);

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
        if ($request->input('category_id') === '') {
            $request->merge(['category_id' => null]);
        }

        $request->validate([
            'business_id'                      => 'required|exists:businesses,id',
            'name'                             => 'required|string|max:255',
            'description'                      => 'nullable|string',
            'sku'                              => 'nullable|string|unique:products,sku',
            'price'                            => 'required|integer|min:0',
            'stock'                            => 'required|integer|min:0',
            'image'                            => 'nullable|image|max:2048',
            'category_id'                      => 'nullable|exists:categories,id',
            // validasi options
            'option_groups'                    => 'nullable|array',
            'option_groups.*.name'             => 'required_with:option_groups|string|max:100',
            'option_groups.*.choices'          => 'required_with:option_groups|array|min:1',
            'option_groups.*.choices.*'        => 'required|string|max:100',
        ]);

        $data = $request->only([
            'business_id', 'name', 'description',
            'sku', 'price', 'stock', 'category_id',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        // Simpan option groups + choices
        $this->syncOptionGroups($product, $request->input('option_groups', []));

        $product->load('business.taxes', 'category', 'optionGroups.choices');

        return response()->json([
            'message' => 'Produk berhasil dibuat.',
            'data'    => $this->formatProduct($product),
        ], 201);
    }

    public function show(Product $product)
    {
        $product->load('business.taxes', 'category', 'optionGroups.choices');
        return response()->json(['data' => $this->formatProduct($product)]);
    }

    public function update(Request $request, Product $product)
    {
        if ($request->input('category_id') === '') {
            $request->merge(['category_id' => null]);
        }

        $request->validate([
            'business_id'                      => 'sometimes|exists:businesses,id',
            'name'                             => 'sometimes|string|max:255',
            'description'                      => 'nullable|string',
            'sku'                              => 'nullable|string|unique:products,sku,' . $product->id,
            'price'                            => 'sometimes|integer|min:0',
            'stock'                            => 'sometimes|integer|min:0',
            'image'                            => 'nullable|image|max:2048',
            'is_active'                        => 'sometimes|boolean',
            'discount_percent'                 => 'nullable|numeric|min:0|max:100',
            'category_id'                      => 'nullable|exists:categories,id',
            // validasi options
            'option_groups'                    => 'nullable|array',
            'option_groups.*.id'               => 'nullable|integer|exists:product_option_groups,id',
            'option_groups.*.name'             => 'required_with:option_groups|string|max:100',
            'option_groups.*.choices'          => 'required_with:option_groups|array|min:1',
            'option_groups.*.choices.*'        => 'required|string|max:100',
        ]);

        $data = $request->only([
            'business_id', 'name', 'description', 'sku',
            'price', 'stock', 'is_active', 'discount_percent',
        ]);

        if ($request->has('category_id')) {
            $data['category_id'] = $request->input('category_id');
        }

        $price   = $data['price'] ?? $product->price;
        $discPct = $data['discount_percent'] ?? $product->discount_percent;
        $data['discounted_price'] = $discPct > 0
            ? (int) round($price * (1 - $discPct / 100))
            : $price;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        // Sync option groups jika dikirim
        if ($request->has('option_groups')) {
            $this->syncOptionGroups($product, $request->input('option_groups', []));
        }

        $product->load('business.taxes', 'category', 'optionGroups.choices');

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

    // ── Sync option groups ────────────────────────────────────────────────────
    // Format input yang diterima:
    // [
    //   { "id": 1, "name": "Rasa", "choices": ["Madu", "Pedas"] },  ← existing group (update)
    //   { "name": "Level", "choices": ["Biasa", "Extra"] },         ← group baru (create)
    // ]
    // Group lama yang tidak ada di input → dihapus (cascade ke choices)
    private function syncOptionGroups(Product $product, array $groups): void
    {
        $incomingIds = collect($groups)
            ->pluck('id')
            ->filter()
            ->toArray();

        // Hapus group yang tidak ada di input
        $product->optionGroups()
            ->whereNotIn('id', $incomingIds)
            ->delete();

        foreach ($groups as $groupData) {
            $groupId = $groupData['id'] ?? null;

            if ($groupId) {
                // Update existing group
                $group = ProductOptionGroup::find($groupId);
                if ($group && $group->product_id === $product->id) {
                    $group->update(['name' => $groupData['name']]);
                    // Replace semua choices
                    $group->choices()->delete();
                    foreach ($groupData['choices'] as $label) {
                        $group->choices()->create(['label' => $label]);
                    }
                }
            } else {
                // Buat group baru + choices-nya
                $group = $product->optionGroups()->create([
                    'name' => $groupData['name'],
                ]);
                foreach ($groupData['choices'] as $label) {
                    $group->choices()->create(['label' => $label]);
                }
            }
        }
    }

    // GET /products/{product}/qr
    public function qrCode(Product $product)
    {
        $qr = QrCode::format('png')
                    ->size(300)
                    ->errorCorrection('H')
                    ->generate((string) $product->id);

        return response($qr, 200)
            ->header('Content-Type', 'image/png');
    }

    // GET /products/qr-sheet
    public function qrSheet()
    {
        $products = Product::where('business_id', auth()->user()->business_id)->get();
        return view('qr-sheet', compact('products'));
    }
}
