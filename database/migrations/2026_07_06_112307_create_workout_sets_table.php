<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_exercise_id')->constrained()->cascadeOnDelete();
            $table->integer('index')->default(0);
            $table->string('type')->default('normal');
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->decimal('reps', 6, 2)->nullable();
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->decimal('duration_seconds', 10, 2)->nullable();
            $table->decimal('rpe', 4, 2)->nullable();
            $table->decimal('custom_metric', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sets');
    }
};
