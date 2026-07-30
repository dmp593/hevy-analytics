<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily per-exercise aggregates, so pages can read hundreds of rows where
 * they read fifteen thousand raw sets. Rebuilt from raw rows (the source
 * of truth) after every sync/import; never written by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_set_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('local_date');
            $table->string('exercise_title');
            $table->string('exercise_template_hevy_id')->nullable();
            $table->string('primary_muscle_group')->nullable();
            $table->unsignedSmallInteger('sets');
            $table->unsignedInteger('reps');
            $table->decimal('tonnage', 12, 2);
            $table->decimal('best_weight', 8, 2);
            $table->unsignedSmallInteger('best_reps');
            $table->timestamps();

            $table->unique(['user_id', 'local_date', 'exercise_title']);
            $table->index(['user_id', 'local_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_set_rollups');
    }
};
