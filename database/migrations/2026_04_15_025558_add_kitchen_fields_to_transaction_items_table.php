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
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->enum('kitchen_status', ['queued', 'cooking', 'paused', 'done'])
                ->default('queued')
                ->after('source');
            $table->timestamp('cooking_started_at')->nullable()->after('kitchen_status');
            $table->timestamp('cooking_done_at')->nullable()->after('cooking_started_at');
            $table->integer('cooking_duration_seconds')->nullable()->after('cooking_done_at');
            $table->integer('pause_duration_seconds')->default(0)->after('cooking_duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn([
                'kitchen_status', 'cooking_started_at', 'cooking_done_at',
                'cooking_duration_seconds', 'pause_duration_seconds',
            ]);
        });
    }
};
