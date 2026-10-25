<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('type', 16)->default('rice');
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('name_bn')->nullable();
            $table->string('name_en')->nullable();
            $table->text('description')->nullable();

            // Rice-specific metadata (nullable for other grocery products).
            $table->string('mill_name')->nullable();
            $table->string('supplier')->nullable();
            $table->string('grain_type')->nullable();
            $table->string('processing_method')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->string('legacy_firebase_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
            $table->index(['is_featured', 'is_active']);
            $table->index(['category_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
