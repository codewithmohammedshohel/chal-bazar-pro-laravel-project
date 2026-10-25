<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')
                ->unique()
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->decimal('on_hand_kg', 12, 4)->default(0);
            $table->decimal('reserved_kg', 12, 4)->default(0);
            $table->decimal('available_kg', 12, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
