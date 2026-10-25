<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link reservations to the order that owns them and guarantee one
     * reservation row per order line, which makes release/fulfil exactly-once.
     */
    public function up(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('product_variant_id')
                ->constrained('orders')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable()->after('status');
            $table->timestamp('released_at')->nullable()->after('fulfilled_at');

            $table->unique(
                ['reference_type', 'reference_id', 'product_variant_id'],
                'stock_reservations_reference_variant_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->dropUnique('stock_reservations_reference_variant_unique');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['fulfilled_at', 'released_at']);
        });
    }
};
