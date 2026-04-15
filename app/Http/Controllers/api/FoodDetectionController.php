<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
            'image'      => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'confidence' => 'nullable|numeric|min:0.1|max:0.95',
        ]);

        try {
            $confidence = $request->input('confidence', 0.35);
            $imageFile  = $request->file('image');

            $response = Http::timeout(30)
                ->attach(
                    'image',
                    file_get_contents($imageFile->path()),
                    $imageFile->getClientOriginalName()
                )
                ->post("{$this->mlApiUrl}/detect", [
                    'confidence' => $confidence,
                ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Detection service error',
                ], 502);
            }

            $result = $response->json();

            // Bisa tambah logic di sini, misal:
            // - simpan history ke DB
            // - mapping label ke produk yang ada di toko
            // - tambah info harga/kalori

            return response()->json([
                'success'        => true,
                'total_detected' => $result['total_detected'],
                'detected_items' => $result['detected_items'],
                'detections'     => $result['detections'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Detection failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}