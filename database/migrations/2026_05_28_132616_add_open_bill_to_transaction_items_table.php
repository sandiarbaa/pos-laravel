<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('order_sequence')->default(1)->after('source');
            $table->timestamp('ordered_at')->nullable()->after('order_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn(['order_sequence', 'ordered_at']);
        });
    }
};
