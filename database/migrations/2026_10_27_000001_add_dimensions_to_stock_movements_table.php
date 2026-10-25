<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make the stock ledger unambiguous: every movement now declares which
     * bucket it affects (physical stock vs. reserved stock) and its direction,
     * plus an optional idempotency key so retries cannot double-apply.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('stock_bucket', 16)->default('physical')->after('movement_type');
            $table->string('direction', 8)->default('in')->after('stock_bucket');
            $table->string('operation_key', 120)->nullable()->unique()->after('reference_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropUnique(['operation_key']);
            $table->dropColumn(['stock_bucket', 'direction', 'operation_key']);
        });
    }
};
