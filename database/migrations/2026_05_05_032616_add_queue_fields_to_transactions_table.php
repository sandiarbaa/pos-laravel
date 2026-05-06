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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('table_number')->nullable()->after('notes');
            $table->enum('queue_status', ['waiting', 'ready', 'taken'])
                ->default('waiting')
                ->after('table_number');
            $table->timestamp('ready_at')->nullable()->after('queue_status');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['table_number', 'queue_status', 'ready_at']);
        });
    }
};
