<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GviStockController extends Controller
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.gvi_stock.url'), '/');
        $this->apiKey  = config('services.gvi_stock.api_key');
    }

    private function headers(): array
    {
        return [
            'X-API-Key' => $this->apiKey,
            'Accept'    => 'application/json',
        ];
    }

    // Fetch semua item types dari GVI-Stock
    public function itemTypes()
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/api/pos/item-types");

        if ($response->failed()) {
            return response()->json(['message' => 'Gagal fetch item types dari GVI-Stock.'], 502);
        }

        return response()->json($response->json());
    }

    // Fetch item variants dari GVI-Stock (dengan filter & search)
    public function itemVariants(Request $request)
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/api/pos/item-variants", $request->only([
                'search',
                'item_type_id',
                'item_id',
                'min_price',
                'max_price',
                'per_page',
                'page',
            ]));

        if ($response->failed()) {
            return response()->json(['message' => 'Gagal fetch produk dari GVI-Stock.'], 502);
        }

        return response()->json($response->json());
    }

    // Fetch detail satu item variant dari GVI-Stock
    public function itemVariantDetail($id)
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/api/pos/item-variants/{$id}");

        if ($response->failed()) {
            return response()->json(['message' => 'Produk GVI tidak ditemukan.'], 404);
        }

        return response()->json($response->json());
    }

    // Update stok GVI-Stock setelah transaksi POS
    // Dipanggil internal dari TransactionController, bukan langsung dari Flutter
    public static function decreaseStock(int $variantId, int $qty): bool
    {
        $baseUrl = rtrim(config('services.gvi_stock.url'), '/');
        $apiKey  = config('services.gvi_stock.api_key');

        $response = Http::withHeaders([
            'X-API-Key' => $apiKey,
            'Accept'    => 'application/json',
        ])->post("{$baseUrl}/api/pos/item-variants/{$variantId}/decrease-stock", [
            'qty' => $qty,
        ]);

        return $response->successful();
    }
}
