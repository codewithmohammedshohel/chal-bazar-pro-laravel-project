<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditable, line-level return records.
     *
     * A batch is the idempotent unit for one admin submission (operation key),
     * while rows in order_returns hold the exact kilograms restored for each
     * ordered line. Cumulative return limits are derived from these rows, not
     * from a single stock-movement operation key.
     */
    public function up(): void
    {
        Schema::create('order_return_batches', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('operation_key', 80);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['order_id', 'operation_key'],
                'order_return_batches_order_operation_unique',
            );
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_batch_id')
                ->constrained('order_return_batches')
                ->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();
            $table->decimal('quantity_kg', 12, 4);
            $table->timestamps();

            $table->unique(
                ['order_return_batch_id', 'order_item_id'],
                'order_returns_batch_item_unique',
            );
            $table->index('order_item_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_returns');
        Schema::dropIfExists('order_return_batches');
    }
};
