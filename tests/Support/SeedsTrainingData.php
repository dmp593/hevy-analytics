<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds realistic training data for analytics tests.
 *
 * Every analytics service reads through SetQuery's join of
 * workout_sets -> workout_exercises -> workouts -> exercise_templates, so tests
 * that exercise anything beyond the empty-data branch need all four tables
 * populated coherently. Doing that by hand in each test is what kept this layer
 * untested; this helper makes it one call.
 */
trait SeedsTrainingData
{
    protected function makeAthlete(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + [
            'sex' => 'male',
            'age' => 32,
            'height_cm' => 178.0,
            'activity_level' => 1.55,
        ]);

        $user->forceFill(['hevy_api_key' => 'test-key'])->save();

        return $user;
    }

    /**
     * @param  array<string, array{0:string, 1:string, 2:array<int,string>}>  $templates
     *                                                                                    keyed by hevy_id => [title, primary muscle, secondary muscles]
     */
    protected function seedExerciseTemplates(User $user, array $templates): void
    {
        foreach ($templates as $hevyId => [$title, $primary, $secondary]) {
            DB::table('exercise_templates')->insert([
                'user_id' => $user->id,
                'hevy_id' => $hevyId,
                'title' => $title,
                'type' => 'weight_reps',
                'primary_muscle_group' => $primary,
                'secondary_muscle_groups' => json_encode($secondary),
                'equipment' => 'barbell',
                'is_custom' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Record one session on $date containing $setsPerExercise working sets of
     * each given exercise template.
     */
    protected function seedWorkout(
        User $user,
        Carbon $date,
        array $templateHevyIds,
        int $setsPerExercise = 3,
        float $weightKg = 100.0,
        int $reps = 5,
        ?float $rpe = null,
    ): int {
        $workoutId = DB::table('workouts')->insertGetId([
            'user_id' => $user->id,
            'hevy_id' => 'w-'.$user->id.'-'.$date->format('YmdHis'),
            'title' => 'Session',
            'start_time' => $date,
            'end_time' => $date->copy()->addHour(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($templateHevyIds as $index => $templateId) {
            $exerciseId = DB::table('workout_exercises')->insertGetId([
                'workout_id' => $workoutId,
                'index' => $index,
                'title' => $templateId,
                'exercise_template_hevy_id' => $templateId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            for ($i = 0; $i < $setsPerExercise; $i++) {
                DB::table('workout_sets')->insert([
                    'workout_exercise_id' => $exerciseId,
                    'index' => $i,
                    'type' => 'normal',
                    'weight_kg' => $weightKg,
                    'reps' => $reps,
                    'rpe' => $rpe,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $workoutId;
    }

    /**
     * Weekly bodyweight measurements moving linearly by $kgPerWeek.
     * A positive rate means the athlete is genuinely gaining weight.
     */
    protected function seedWeightTrend(
        User $user,
        float $startKg,
        float $kgPerWeek,
        int $weeks,
        ?Carbon $endingOn = null,
    ): void {
        $end = $endingOn ?? Carbon::now();

        for ($i = 0; $i < $weeks; $i++) {
            $weeksAgo = $weeks - 1 - $i;
            $user->bodyMeasurements()->create([
                'date' => $end->copy()->subWeeks($weeksAgo)->toDateString(),
                'weight_kg' => round($startKg + $kgPerWeek * $i, 2),
                'fat_percent' => 15.0,
            ]);
        }
    }
}
