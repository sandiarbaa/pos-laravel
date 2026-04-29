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
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $user = $request->user()->load('business');
        $businessId = $user->business_id;
        $modelKey = $user->business?->model_key;

        if (!$businessId) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terikat ke bisnis',
            ], 422);
        }

        if (!$modelKey) {
            return response()->json([
                'success' => false,
                'message' => 'Bisnis belum punya model AI, hubungi superadmin',
            ], 422);
        }

        try {
            $imageFile = $request->file('image');

            $response = Http::timeout(30)
                ->attach(
                    'file',
                    file_get_contents($imageFile->path()),
                    $imageFile->getClientOriginalName()
                )
                ->post("{$this->mlApiUrl}/detect", [
                    'model_key' => $modelKey,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Detection service error: ' . $response->body(),
                ], 502);
            }

            $result = $response->json();
            $detections = $result['detections'] ?? [];

            $items = [];
            foreach ($detections as $det) {
                $label = $det['label'];
                $displayName = str_replace('_', ' ', $label);

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
                'items'   => $items,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Detection failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
