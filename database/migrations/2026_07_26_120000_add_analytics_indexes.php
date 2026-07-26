<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index the join path every analytics page depends on.
 *
 * SetQuery joins workout_sets -> workout_exercises -> workouts ->
 * exercise_templates and filters on user_id + start_time. None of those foreign
 * keys carried an index: workout_sets had no index at all, not even on its own
 * join key, so every page full-scanned the whole multi-tenant set table. The
 * same gap makes deleting a user O(n^2), which matters now that account deletion
 * is a GDPR obligation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sets', function (Blueprint $table) {
            $table->index('workout_exercise_id', 'workout_sets_exercise_idx');
            // Warm-ups are excluded from nearly every query.
            $table->index(['workout_exercise_id', 'type'], 'workout_sets_exercise_type_idx');
        });

        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->index('workout_id', 'workout_exercises_workout_idx');
        });

        Schema::table('workouts', function (Blueprint $table) {
            // Composite: every read is scoped to a user and bounded by date.
            $table->index(['user_id', 'start_time'], 'workouts_user_start_idx');
        });

        Schema::table('body_measurements', function (Blueprint $table) {
            $table->index(['user_id', 'date'], 'body_measurements_user_date_idx');
        });

        Schema::table('intake_logs', function (Blueprint $table) {
            $table->index(['user_id', 'date'], 'intake_logs_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('workout_sets', function (Blueprint $table) {
            $table->dropIndex('workout_sets_exercise_idx');
            $table->dropIndex('workout_sets_exercise_type_idx');
        });

        Schema::table('workout_exercises', function (Blueprint $table) {
            $table->dropIndex('workout_exercises_workout_idx');
        });

        Schema::table('workouts', function (Blueprint $table) {
            $table->dropIndex('workouts_user_start_idx');
        });

        Schema::table('body_measurements', function (Blueprint $table) {
            $table->dropIndex('body_measurements_user_date_idx');
        });

        Schema::table('intake_logs', function (Blueprint $table) {
            $table->dropIndex('intake_logs_user_date_idx');
        });
    }
};
