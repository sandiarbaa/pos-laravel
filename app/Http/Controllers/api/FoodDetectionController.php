<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FoodDetectionController extends Controller
{
    public function detect(Request $request)
    {
        $request->validate([
            'image'       => 'required|image|max:5120',
            'business_id' => 'required|exists:businesses,id',
        ]);

        // Kirim foto ke FastAPI
        $response = Http::attach(
            'file',
            file_get_contents($request->file('image')->getPathname()),
            'image.jpg'
        )->post('http://localhost:8007/detect');

        if (!$response->ok()) {
            return response()->json(['message' => 'Gagal deteksi'], 500);
        }

        $detections = $response->json()['detections'] ?? [];

        if (empty($detections)) {
            return response()->json([
                'message' => 'Tidak ada makanan terdeteksi',
                'items'   => [],
            ]);
        }

        // Match ke tabel products berdasarkan nama
        $items = [];
        foreach ($detections as $det) {
            if ($det['confidence'] < 0.5) continue;

            // Ubah underscore jadi spasi untuk matching
            // nasi_goreng → nasi goreng
            $keyword = str_replace('_', ' ', $det['label']);

            $product = Product::where('business_id', $request->business_id)
                ->where('is_active', true)
                ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($keyword) . '%'])
                ->first();

            if ($product) {
                $items[] = [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'price'        => $product->discounted_price > 0
                                        ? $product->discounted_price
                                        : $product->price,
                    'quantity'     => 1,
                    'confidence'   => $det['confidence'],
                    'source'       => 'pos',
                ];
            } else {
                // Produk ga ketemu di database
                $items[] = [
                    'product_id'   => null,
                    'product_name' => $det['label'],
                    'price'        => 0,
                    'quantity'     => 1,
                    'confidence'   => $det['confidence'],
                    'source'       => 'pos',
                    'not_found'    => true,
                ];
            }
        }

        return response()->json([
            'message' => 'Deteksi berhasil',
            'items'   => $items,
        ]);
    }
}
