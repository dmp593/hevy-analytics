<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('lean_bulk');
            $table->boolean('is_active')->default(true);
            // Overridable params; null => use preset default for the type
            $table->decimal('calorie_adjustment_pct', 5, 2)->nullable();
            $table->decimal('protein_g_per_kg', 4, 2)->nullable();
            $table->decimal('fat_g_per_kg', 4, 2)->nullable();
            $table->decimal('target_rate_pct_bw_per_week', 4, 2)->nullable();
            $table->json('training')->nullable();
            $table->date('started_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
