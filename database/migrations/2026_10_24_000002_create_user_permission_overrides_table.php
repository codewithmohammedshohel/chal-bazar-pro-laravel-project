<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user permission overrides. A row can either grant or explicitly deny
     * a single permission; the override always wins over the role default.
     */
    public function up(): void
    {
        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 64);
            $table->boolean('granted')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
    }
};
