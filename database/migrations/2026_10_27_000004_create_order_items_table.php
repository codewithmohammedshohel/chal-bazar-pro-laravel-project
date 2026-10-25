<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // Historical snapshots — never re-read from the live product later.
            $table->string('product_title');
            $table->string('variant_label');
            $table->string('unit', 16);
            $table->decimal('weight_kg', 8, 3);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 12, 2);

            $table->timestamps();

            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
