<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('hevy_id')->nullable()->index();
            $table->date('date')->index();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('lean_mass_kg', 6, 2)->nullable();
            $table->decimal('fat_percent', 5, 2)->nullable();
            $table->decimal('neck_cm', 5, 1)->nullable();
            $table->decimal('shoulder_cm', 5, 1)->nullable();
            $table->decimal('chest_cm', 5, 1)->nullable();
            $table->decimal('left_bicep_cm', 5, 1)->nullable();
            $table->decimal('right_bicep_cm', 5, 1)->nullable();
            $table->decimal('left_forearm_cm', 5, 1)->nullable();
            $table->decimal('right_forearm_cm', 5, 1)->nullable();
            $table->decimal('abdomen', 5, 1)->nullable();
            $table->decimal('waist', 5, 1)->nullable();
            $table->decimal('hips', 5, 1)->nullable();
            $table->decimal('left_thigh', 5, 1)->nullable();
            $table->decimal('right_thigh', 5, 1)->nullable();
            $table->decimal('left_calf', 5, 1)->nullable();
            $table->decimal('right_calf', 5, 1)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_measurements');
    }
};
