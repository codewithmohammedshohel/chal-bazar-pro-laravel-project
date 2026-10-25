<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->string('status', 24)->default('pending');
            $table->string('payment_mode', 16)->default('COD');

            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('total', 12, 2);

            $table->text('delivery_address');
            $table->decimal('delivery_lat', 10, 7)->nullable();
            $table->decimal('delivery_lng', 10, 7)->nullable();
            $table->decimal('distance_km', 8, 3)->nullable();

            $table->text('customer_note')->nullable();

            $table->string('idempotency_key', 64)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 16)->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
