<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nutritions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('serving_size')->default(100);
            $table->integer('calories')->default(0);
            $table->float('protein')->default(0);
            $table->float('carbs')->default(0);
            $table->float('fat')->default(0);
            $table->float('fiber')->default(0);
            $table->float('sugar')->default(0);
            $table->float('sodium')->default(0);
            $table->text('ai_raw_response')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nutritions');
    }
};
