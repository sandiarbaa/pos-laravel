<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Diskon per produk — opsional, set oleh admin
            $table->decimal('discount_percent', 5, 2)
                ->default(0)
                ->after('price')
                ->comment('Persentase diskon, 0 = tidak ada diskon');

            // Harga setelah diskon — dihitung otomatis, disimpan buat kemudahan query
            $table->integer('discounted_price')
                ->default(0)
                ->after('discount_percent')
                ->comment('Harga setelah diskon, sama dengan price kalau discount_percent = 0');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discounted_price']);
        });
    }
};
