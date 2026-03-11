<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Kasir di-assign ke 1 bisnis, admin & superadmin null
            $table->foreignId('business_id')
                ->nullable()
                ->after('is_active')
                ->constrained('businesses')
                ->nullOnDelete();

            // Kasir punya admin (owner), admin & superadmin null
            $table->foreignId('owner_id')
                ->nullable()
                ->after('business_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['business_id', 'owner_id']);
        });
    }
};
