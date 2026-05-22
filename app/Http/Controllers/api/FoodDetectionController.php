<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use Illuminate\Support\Facades\Log;


class FoodDetectionController extends Controller
{
    private string $mlApiUrl;
    private const CATEGORY_MODEL_SUFFIX = '_category';

    public function __construct()
    {
        $this->mlApiUrl = (string) config('services.ml_api.url', 'http://localhost:5000');
    }

    public function detect(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $user       = $request->user()->load('business');
        $businessId = $user->business_id;
        $modelKey   = $user->business?->model_key;

        $logContext = [
            'user_id'     => $user->id,
            'business_id' => $businessId,
            'model_key'   => $modelKey,
            'image'       => $request->file('image')?->getClientOriginalName(),
        ];

        if (!$businessId) {
            Log::warning('[FoodDetection] No business attached', $logContext);
            return response()->json(['success' => false, 'message' => 'User tidak terikat ke bisnis'], 422);
        }

        if (!$modelKey) {
            Log::warning('[FoodDetection] No model_key', $logContext);
            return response()->json(['success' => false, 'message' => 'Bisnis belum punya model AI, hubungi superadmin'], 422);
        }

        try {
            $imageFile = $request->file('image');
            $start     = microtime(true);

            $response = Http::timeout(30)
                ->attach('file', file_get_contents($imageFile->path()), $imageFile->getClientOriginalName())
                ->post("{$this->mlApiUrl}/detect", ['model_key' => $modelKey]);

            $logContext['elapsed_ms']  = round((microtime(true) - $start) * 1000);
            $logContext['status_code'] = $response->status();

            if ($response->failed()) {
                Log::error('[FoodDetection] ML API error', array_merge($logContext, [
                    'response' => $response->body(),
                ]));
                return response()->json(['success' => false, 'message' => 'Detection service error: ' . $response->body()], 502);
            }

            $result     = $response->json();
            $detections = $result['detections'] ?? [];

            if (empty($detections)) {
                Log::warning('[FoodDetection] Empty detections', array_merge($logContext, ['raw' => $result]));
            }

            if ($this->isCategoryModel($modelKey)) {
                $categories = $this->buildCategorySuggestions($businessId, $modelKey, $detections);
                Log::info('[FoodDetection] Category mode ok', array_merge($logContext, ['count' => count($categories)]));
                return response()->json(['success' => true, 'mode' => 'category', 'categories' => $categories]);
            }

            $items = [];
            $notFound = [];
            foreach ($detections as $det) {
                $item = $this->buildExactMatchItem($businessId, $det);
                $items[] = $item;
                if ($item['not_found']) $notFound[] = $det['label'];
            }

            if (!empty($notFound)) {
                Log::warning('[FoodDetection] Labels not matched', array_merge($logContext, ['not_found' => $notFound]));
            }

            Log::info('[FoodDetection] Exact match ok', array_merge($logContext, ['not_found_count' => count($notFound)]));
            return response()->json(['success' => true, 'items' => $items]);

        } catch (\Exception $e) {
            Log::error('[FoodDetection] Exception', array_merge($logContext, [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]));
            return response()->json(['success' => false, 'message' => 'Detection failed: ' . $e->getMessage()], 500);
        }
    }

    private function isCategoryModel(?string $modelKey): bool
    {
        return $modelKey !== null && str_ends_with($modelKey, self::CATEGORY_MODEL_SUFFIX);
    }

    private function buildExactMatchItem(int $businessId, array $det): array
    {
        $label = $det['label'];
        $displayName = str_replace('_', ' ', $label);

        $product = Product::where('business_id', $businessId)
            ->where(function ($q) use ($label, $displayName) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($displayName) . '%'])
                  ->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($label) . '%']);
            })
            ->first();

        return [
            'product_id'   => $product?->id,
            'product_name' => $product?->name ?? $displayName,
            'price'        => $product?->price ?? 0,
            'confidence'   => $det['confidence'],
            'not_found'    => $product === null,
        ];
    }

    private function buildCategorySuggestions(int $businessId, string $modelKey, array $detections): array
    {
        $suggestions = [];

        foreach ($detections as $det) {
            $label = $det['label'] ?? '';
            $products = $this->findProductsByCategoryLabel($businessId, $modelKey, $label);

            $suggestions[] = [
                'label'          => $label,
                'display_name'   => $det['display_name'] ?? str_replace('_', ' ', $label),
                'confidence'     => $det['confidence'] ?? 0,
                'candidate_count'=> $products->count(),
                'candidates'     => $products->map(fn ($product) => [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'price'        => $product->price,
                ])->values(),
            ];
        }

        return $suggestions;
    }

    private function findProductsByCategoryLabel(int $businessId, string $modelKey, string $label)
    {
        $products = Product::query()
            ->where('business_id', $businessId)
            ->where('is_active', true);

        $keywordMap = $this->categoryKeywordMap($modelKey);
        $keywords = $keywordMap[$label] ?? [];

        if (empty($keywords)) {
            return collect();
        }

        $products->where(function ($q) use ($keywords) {
            foreach ($keywords as $keyword) {
                $q->orWhereRaw('LOWER(name) LIKE ?', ['%' . strtolower($keyword) . '%']);
            }
        });

        return $products
            ->orderBy('name')
            ->get();
    }

    private function categoryKeywordMap(string $modelKey): array
    {
        return match ($modelKey) {
            'ayam_maya_category' => [
                'minuman' => ['coklat', 'jeruk', 'teh', 'lemon tea', 'beras kencur', 'air mineral'],
                'ayam' => ['ayam'],
                'bebek' => ['bebek'],
                'iga' => ['iga', 'sop iga'],
                'lele' => ['lele'],
                'ceker' => ['ceker'],
                'jeroan_telur' => ['kepala', 'ampela', 'telor'],
                'side_dish' => ['egg roll', 'tempe', 'tahu'],
                'nasi_ayam' => ['paket nasi', 'nasi uduk ayam', 'nasi ayam'],
                'nasi_bebek' => ['nasi bebek', 'nasi uduk bebek'],
                'nasi_iga' => ['nasi iga'],
                'sayuran' => ['kol goreng', 'terong', 'cah kangkung', 'cah taoge'],
                'spesial' => ['nasi goreng ayam maya'],
            ],
            default => [],
        };
    }
}
