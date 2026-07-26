<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->string('goal_type')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('lean_mass_kg', 6, 2)->nullable();
            $table->decimal('bmr', 8, 1)->nullable();
            $table->string('bmr_formula')->nullable();
            $table->decimal('tdee', 8, 1)->nullable();
            $table->decimal('target_calories', 8, 1)->nullable();
            $table->decimal('protein_g', 7, 1)->nullable();
            $table->decimal('fat_g', 7, 1)->nullable();
            $table->decimal('carb_g', 7, 1)->nullable();
            $table->json('basis')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_targets');
    }
};
