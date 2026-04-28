<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Product;

class FoodDetectionController extends Controller
{
    private string $mlApiUrl;

    public function __construct()
    {
        $this->mlApiUrl = config('services.ml_api.url', 'http://localhost:5000');
    }

    public function detect(Request $request)
    {
        $request->validate([
            'image'       => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'business_id' => 'nullable|integer',
            'confidence'  => 'nullable|numeric|min:0.1|max:0.95',
        ]);

        try {
            $imageFile  = $request->file('image');
            $businessId = $request->input('business_id', 1);

            // Kirim ke Python dengan field name 'file' (sesuai FastAPI)
            $response = Http::timeout(30)
                ->attach(
                    'file',                                    // ← fix: ganti 'image' → 'file'
                    file_get_contents($imageFile->path()),
                    $imageFile->getClientOriginalName()
                )
                ->post("{$this->mlApiUrl}/detect");

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Detection service error: ' . $response->body(),
                ], 502);
            }

            $result     = $response->json();
            $detections = $result['detections'] ?? [];

            // Mapping label → produk di database
            $items = [];
            foreach ($detections as $det) {
                $label = $det['label'];        // e.g. "jus_mangga"
                $displayName = str_replace('_', ' ', $label);  // "jus mangga"

                // Cari produk by business_id, cocokkan nama
                $product = Product::where('business_id', $businessId)
                    ->where(function ($q) use ($label, $displayName) {
                        $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($displayName) . '%'])
                          ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($label) . '%']);
                    })
                    ->first();

                $items[] = [
                    'product_id'   => $product?->id,
                    'product_name' => $product?->name ?? $displayName,
                    'price'        => $product?->price ?? 0,
                    'confidence'   => $det['confidence'],
                    'not_found'    => $product === null,
                ];
            }

            return response()->json([
                'success' => true,
                'items'   => $items,         // ← Flutter expect 'items'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Detection failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
