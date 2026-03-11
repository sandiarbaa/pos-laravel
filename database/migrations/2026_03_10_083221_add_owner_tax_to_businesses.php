<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Admin yang punya bisnis ini
            $table->foreignId('owner_id')
                ->nullable()
                ->after('is_active')
                ->constrained('users')
                ->nullOnDelete();

            // Pajak bisnis — misal "PPN", 11
            $table->string('tax_name')->nullable()->after('owner_id');   // contoh: "PPN"
            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_name'); // contoh: 11.00
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['owner_id', 'tax_name', 'tax_rate']);
        });
    }
};
