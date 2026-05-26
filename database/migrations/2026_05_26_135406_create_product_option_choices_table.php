<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_option_choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')
                  ->constrained('product_option_groups')
                  ->cascadeOnDelete();
            $table->string('label'); // misal "Madu", "Pedas", "Original"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_choices');
    }
};
