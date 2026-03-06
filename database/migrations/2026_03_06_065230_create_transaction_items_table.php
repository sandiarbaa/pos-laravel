<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();

            // Produk dari POS sendiri
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Produk dari GVI-Stock (simpan id variant nya aja)
            $table->unsignedBigInteger('gvi_item_variant_id')->nullable();
            $table->string('gvi_item_variant_name')->nullable(); // snapshot nama waktu transaksi

            // Info produk di-snapshot saat transaksi (biar ga berubah kalau produk diedit)
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->integer('price');
            $table->integer('quantity');
            $table->integer('subtotal');

            // Sumber produk: 'pos' atau 'gvi'
            $table->enum('source', ['pos', 'gvi'])->default('pos');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};
