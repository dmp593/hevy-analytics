<?php

namespace App\Services\Analytics;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Maintains workout_set_rollups: one row per user/local-day/exercise with
 * the aggregates every volume-shaped page needs. Raw sets remain the source
 * of truth — this is a derived table, rebuilt wholesale after each sync or
 * import inside one transaction, which at one aggregation query per rebuild
 * is cheaper than being clever about deltas.
 *
 * Reliable e1RM is deliberately NOT here: it needs the RPE-aware clamps in
 * OneRepMax, so strength trends keep reading raw rows.
 */
class RollupBuilder
{
    public function rebuild(User $user): void
    {
        $tz = $user->resolvedTimezone();

        DB::transaction(function () use ($user, $tz) {
            DB::table('workout_set_rollups')->where('user_id', $user->id)->delete();

            DB::insert(<<<'SQL'
                INSERT INTO workout_set_rollups
                    (user_id, local_date, exercise_title, exercise_template_hevy_id,
                     primary_muscle_group, sets, reps, tonnage, best_weight, best_reps,
                     created_at, updated_at)
                SELECT
                    w.user_id,
                    (w.start_time AT TIME ZONE 'UTC' AT TIME ZONE ?)::date,
                    we.title,
                    MAX(we.exercise_template_hevy_id),
                    MAX(t.primary_muscle_group),
                    COUNT(*),
                    COALESCE(SUM(s.reps), 0),
                    COALESCE(SUM(s.weight_kg * s.reps), 0),
                    COALESCE(MAX(s.weight_kg), 0),
                    COALESCE(MAX(s.reps), 0),
                    NOW(), NOW()
                FROM workout_sets s
                JOIN workout_exercises we ON s.workout_exercise_id = we.id
                JOIN workouts w ON we.workout_id = w.id
                LEFT JOIN exercise_templates t
                    ON t.hevy_id = we.exercise_template_hevy_id AND t.user_id = w.user_id
                WHERE w.user_id = ?
                  AND w.start_time IS NOT NULL
                  AND s.type != 'warmup'
                GROUP BY w.user_id, 2, we.title
            SQL, [$tz, $user->id]);
        });
    }
}
