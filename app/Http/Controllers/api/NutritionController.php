<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Nutrition;
use App\Models\Product;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NutritionController extends Controller
{
    private $geminiApiKey;
    private $geminiUrl;

    public function __construct()
    {
        $this->geminiApiKey = env('GEMINI_API_KEY');
        $this->geminiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent';
    }

    // Ambil nutrisi by product_id
    public function show($productId)
    {
        $product = Product::findOrFail($productId);
        $nutrition = $product->nutrition;

        if (!$nutrition) {
            return response()->json([
                'success' => false,
                'message' => 'Data nutrisi belum tersedia untuk produk ini',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $nutrition,
        ]);
    }

    public function generate(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        try {
            $client = new Client();

            $prompt = "Kamu adalah ahli gizi. Berikan estimasi kandungan nutrisi untuk makanan berikut: \"{$product->name}\".
            
    Berikan response HANYA dalam format JSON berikut, tanpa penjelasan tambahan:
    {
        \"serving_size\": 300,
        \"calories\": 520,
        \"protein\": 18.5,
        \"carbs\": 65.0,
        \"fat\": 20.0,
        \"fiber\": 3.0,
        \"sugar\": 5.0,
        \"sodium\": 450.0
    }

    Keterangan:
    - serving_size dalam gram (1 porsi normal)
    - calories dalam kkal
    - protein, carbs, fat, fiber, sugar dalam gram
    - sodium dalam miligram
    - Sesuaikan dengan porsi makanan Indonesia pada umumnya";

            $parts = [['text' => $prompt]];

            if ($product->image) {
                $imagePath = storage_path('app/public/' . $product->image);
                if (file_exists($imagePath)) {
                    $imageData = base64_encode(file_get_contents($imagePath));
                    $mimeType = mime_content_type($imagePath);
                    $parts[] = [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $imageData,
                        ]
                    ];
                }
            }

            $maxRetries = 3;
            $attempt = 0;
            $response = null;

            while ($attempt < $maxRetries) {
                try {
                    $response = $client->post($this->geminiUrl . '?key=' . $this->geminiApiKey, [
                        'json' => [
                            'contents' => [
                                ['parts' => $parts]
                            ],
                            'generationConfig' => [
                                'temperature' => 0.2,
                                'responseMimeType' => 'application/json',
                            ]
                        ]
                    ]);
                    break;
                } catch (\GuzzleHttp\Exception\ServerException $e) {
                    $attempt++;
                    if ($attempt >= $maxRetries) throw $e;
                    sleep(2);
                }
            }

            $result = json_decode($response->getBody()->getContents(), true);
            $rawText = $result['candidates'][0]['content']['parts'][0]['text'];
            $nutritionData = json_decode($rawText, true);

            $nutrition = Nutrition::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'serving_size' => $nutritionData['serving_size'] ?? 100,
                    'calories'     => $nutritionData['calories'] ?? 0,
                    'protein'      => $nutritionData['protein'] ?? 0,
                    'carbs'        => $nutritionData['carbs'] ?? 0,
                    'fat'          => $nutritionData['fat'] ?? 0,
                    'fiber'        => $nutritionData['fiber'] ?? 0,
                    'sugar'        => $nutritionData['sugar'] ?? 0,
                    'sodium'       => $nutritionData['sodium'] ?? 0,
                    'ai_raw_response' => $rawText,
                    'is_verified'  => false,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Nutrisi berhasil di-generate oleh AI',
                'data'    => $nutrition,
            ]);

        } catch (\Exception $e) {
            Log::error('Gemini API Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal generate nutrisi: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Update manual oleh admin (setelah verifikasi)
    public function update(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $validated = $request->validate([
            'serving_size' => 'required|integer',
            'calories'     => 'required|integer',
            'protein'      => 'required|numeric',
            'carbs'        => 'required|numeric',
            'fat'          => 'required|numeric',
            'fiber'        => 'required|numeric',
            'sugar'        => 'required|numeric',
            'sodium'       => 'required|numeric',
            'is_verified'  => 'boolean',
        ]);

        $nutrition = Nutrition::updateOrCreate(
            ['product_id' => $product->id],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Data nutrisi berhasil diupdate',
            'data'    => $nutrition,
        ]);
    }
}
