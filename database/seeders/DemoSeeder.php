<?php

namespace Database\Seeders;

use App\Models\BodyMeasurement;
use App\Models\ExerciseTemplate;
use App\Models\Routine;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A believable training history, not random noise.
 *
 * The point of a demo account is to exercise the analytics, so the data has to
 * have the shape real training has: a four-day split, loads that climb slowly
 * with a deload every fifth week, warm-ups that must be excluded from hard-set
 * counts, RPE that drifts up within a block, and a bodyweight trend the
 * regression can actually find. Random numbers would make every chart look
 * plausible and every metric meaningless.
 */
class DemoSeeder
{
    /** title => [primary muscle, secondary muscles, starting load kg] */
    private const PROGRAMME = [
        'push' => [
            ['Bench Press (Barbell)', 'chest', ['triceps', 'shoulders'], 70],
            ['Overhead Press (Barbell)', 'shoulders', ['triceps'], 45],
            ['Triceps Pushdown (Cable)', 'triceps', [], 30],
        ],
        'pull' => [
            ['Barbell Row', 'upper_back', ['biceps'], 65],
            ['Lat Pulldown (Cable)', 'lats', ['biceps'], 55],
            ['Bicep Curl (Dumbbell)', 'biceps', [], 14],
        ],
        'legs' => [
            ['Squat (Barbell)', 'quadriceps', ['glutes'], 100],
            ['Romanian Deadlift (Barbell)', 'hamstrings', ['glutes', 'lower_back'], 90],
            ['Leg Curl (Machine)', 'hamstrings', [], 40],
        ],
        'upper' => [
            ['Incline Bench Press (Barbell)', 'chest', ['shoulders', 'triceps'], 60],
            ['Pull Up', 'lats', ['biceps'], 0],
            ['Lateral Raise (Dumbbell)', 'shoulders', [], 10],
        ],
    ];

    public function run(string $email = 'demo@example.test', int $weeks = 40): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Demo Lifter', 'password' => bcrypt('password')],
        );

        $user->forceFill([
            'email_verified_at' => now(),
            'is_demo' => true,
            'hevy_api_key' => 'demo-key-not-a-real-credential',
            'sex' => 'male',
            'age' => 32,
            'height_cm' => 178.0,
            'activity_level' => 1.55,
            'timezone' => 'Europe/Lisbon',
            // Null, always: a stored locale on this shared row would outrank
            // every visitor's own session choice in SetLocale.
            'locale' => null,
            'body_fat_source' => 'scale',
            'hevy_last_synced_at' => now(),
        ])->save();

        $this->reset($user);

        $user->goals()->create([
            'type' => 'lean_bulk',
            'is_active' => true,
            'started_at' => now()->subWeeks($weeks)->toDateString(),
        ]);

        $templates = $this->seedTemplates($user);
        $this->seedRoutines($user, $templates);
        $this->seedTraining($user, $templates, $weeks);
        $this->seedBody($user, $weeks);
        $this->seedIntake($user);
        $this->seedAnalysis($user);

        return $user->refresh();
    }

    /**
     * A canned written analysis, so the AI page demonstrates its output without
     * a visitor's curiosity costing a provider call — the demo account is
     * read-only and could not generate one anyway.
     */
    private function seedAnalysis(User $user): void
    {
        $user->aiAnalyses()->create([
            'scope' => 'deep_analysis',
            'model' => 'demo',
            'response' => implode("\n\n", [
                '**Volume.** Nine of eleven muscles sit below MEV for the last month — upper back (2.8/wk against 10) and quads (2.8 against 8) are the widest gaps. The push/pull ratio itself is fine at 1.19, so this is not imbalance, it is total volume: the two training days currently held are not enough sets to grow most of what is being trained. Priority for the next four weeks: a third weekly session carrying ~8 sets of upper back and ~6 of quads.',
                '**Strength.** e1RM trends on the big lifts are rising steadily (+0.8 kg/wk on squat, R² 0.99) — progressive overload is working where volume is sufficient. Keep the double-progression scheme; nothing to change.',
                '**Body composition.** Weight is up but at +0.01%BW/wk against a +0.35 target, with partitioning at ~46% lean over 13 readings. The bulk has effectively stalled: intake is not covering the surplus the goal asks for. Add 150–250 kcal/day, mostly carbs, and re-check the weight trend in three weeks.',
                '**Nutrition.** Measured maintenance (~3,060 kcal) sits close to the formula estimate, so the targets are trustworthy. Protein at 163 g (2.0 g/kg) is adequate; the shortfall is total energy, not macro split.',
            ]),
        ]);
    }

    /**
     * The routines the logged sessions were performed from.
     *
     * Workouts already carry a routine_hevy_id, so without the matching Routine
     * rows the Routines page is empty and the routine analytics have nothing to
     * summarise — which is precisely the page you would want to look at.
     */
    private function seedRoutines(User $user, array $templates): void
    {
        foreach (self::PROGRAMME as $day => $exercises) {
            $routine = Routine::create([
                'user_id' => $user->id,
                'hevy_id' => 'demo-r-'.$day,
                'title' => ucfirst($day).' day',
                'hevy_created_at' => now(),
                'hevy_updated_at' => now(),
            ]);

            foreach ($exercises as $index => [$title, $_p, $_s, $load]) {
                RoutineExercise::create([
                    'routine_id' => $routine->id,
                    'index' => $index,
                    'title' => $title,
                    'exercise_template_hevy_id' => $templates[$title],
                    'rest_seconds' => 150,
                    'sets' => array_fill(0, 3, [
                        'type' => 'normal',
                        'weight_kg' => $load,
                        'reps' => 8,
                    ]),
                ]);
            }
        }
    }

    private function reset(User $user): void
    {
        WorkoutSet::whereHas('workoutExercise.workout', fn ($q) => $q->where('user_id', $user->id))->delete();
        WorkoutExercise::whereHas('workout', fn ($q) => $q->where('user_id', $user->id))->delete();
        $user->workouts()->delete();
        $user->bodyMeasurements()->delete();
        $user->routines()->delete();
        $user->exerciseTemplates()->delete();
        $user->goals()->delete();
        $user->intakeLogs()->delete();
        $user->nutritionTargets()->delete();
        $user->aiAnalyses()->delete();
        // Stale sync logs would show a failed/queued banner to every visitor.
        $user->syncLogs()->delete();
    }

    /** @return array<string, string> exercise title => hevy_id */
    private function seedTemplates(User $user): array
    {
        $ids = [];

        foreach (self::PROGRAMME as $exercises) {
            foreach ($exercises as [$title, $primary, $secondary, $_load]) {
                if (isset($ids[$title])) {
                    continue;
                }

                $template = ExerciseTemplate::create([
                    'user_id' => $user->id,
                    'hevy_id' => 'demo-'.str($title)->slug(),
                    'title' => $title,
                    'type' => 'weight_reps',
                    'primary_muscle_group' => $primary,
                    'secondary_muscle_groups' => $secondary,
                    'equipment' => 'barbell',
                    'is_custom' => false,
                ]);

                $ids[$title] = $template->hevy_id;
            }
        }

        return $ids;
    }

    private function seedTraining(User $user, array $templates, int $weeks): void
    {
        $days = array_keys(self::PROGRAMME);
        $tz = $user->resolvedTimezone();
        $start = Carbon::now($tz)->subWeeks($weeks)->startOfWeek();
        $session = 0;

        for ($week = 0; $week < $weeks; $week++) {
            // Every fifth week is a deload: less load, fewer sets. Without it the
            // trend lines are implausibly straight and stall detection has
            // nothing to find.
            $deload = ($week + 1) % 5 === 0;

            foreach ($days as $i => $day) {
                $at = $start->copy()->addWeeks($week)->addDays($i * 2)->setTime(18, 30);
                if ($at->isFuture()) {
                    continue;
                }

                $session++;
                $workout = Workout::create([
                    'user_id' => $user->id,
                    'hevy_id' => 'demo-w-'.$session,
                    'title' => ucfirst($day),
                    'routine_hevy_id' => 'demo-r-'.$day,
                    'start_time' => $at->copy()->utc(),
                    'end_time' => $at->copy()->addMinutes(75)->utc(),
                    'hevy_created_at' => $at->copy()->utc(),
                    'hevy_updated_at' => $at->copy()->utc(),
                ]);

                foreach (self::PROGRAMME[$day] as $index => [$title, $_p, $_s, $baseLoad]) {
                    $this->seedExercise($workout, $templates[$title], $title, $index, $baseLoad, $week, $deload);
                }
            }
        }
    }

    private function seedExercise(
        Workout $workout,
        string $templateId,
        string $title,
        int $index,
        float $baseLoad,
        int $week,
        bool $deload,
    ): void {
        $exercise = WorkoutExercise::create([
            'workout_id' => $workout->id,
            'index' => $index,
            'title' => $title,
            'exercise_template_hevy_id' => $templateId,
        ]);

        // ~0.6% a week, which is roughly what a real intermediate manages.
        $load = round($baseLoad * (1 + $week * 0.006) * ($deload ? 0.85 : 1), 1);
        $workingSets = $deload ? 2 : 3;

        // One warm-up per exercise: hard-set counting must exclude it, and a demo
        // that never contains one would hide a regression in that filter.
        WorkoutSet::create([
            'workout_exercise_id' => $exercise->id,
            'index' => 0,
            'type' => 'warmup',
            'weight_kg' => round($load * 0.55, 1),
            'reps' => 10,
        ]);

        for ($s = 1; $s <= $workingSets; $s++) {
            WorkoutSet::create([
                'workout_exercise_id' => $exercise->id,
                'index' => $s,
                'type' => 'normal',
                'weight_kg' => $load,
                'reps' => max(4, 9 - $s),
                // RPE climbs across the sets, and across a training block.
                'rpe' => min(10, 7 + $s * 0.5 + ($deload ? -1.5 : ($week % 5) * 0.2)),
            ]);
        }
    }

    private function seedBody(User $user, int $weeks): void
    {
        $rows = [];

        for ($i = 0; $i <= $weeks; $i++) {
            $date = Carbon::now($user->resolvedTimezone())->subWeeks($weeks - $i)->startOfWeek();

            // A genuine lean-bulk trend with weekly noise on top, so the
            // regression has something real to find but is not handed a
            // perfectly straight line.
            $weight = 78 + $i * 0.09 + sin($i / 2.5) * 0.35;
            $fat = 14.5 + $i * 0.012 + cos($i / 3) * 0.25;

            $rows[] = [
                'user_id' => $user->id,
                'date' => $date->toDateString(),
                'weight_kg' => round($weight, 1),
                'fat_percent' => round($fat, 1),
                'neck_cm' => 39.0,
                'chest_cm' => round(102 + $i * 0.04, 1),
                'abdomen' => round(84 + $i * 0.01, 1),
                'waist' => round(80 + $i * 0.01, 1),
                'right_bicep_cm' => round(37 + $i * 0.02, 1),
                'left_bicep_cm' => round(36.8 + $i * 0.02, 1),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table((new BodyMeasurement)->getTable())->insert($rows);
    }

    /**
     * Daily intake for the last four weeks.
     *
     * Without this the demo account can never show adaptive maintenance, which
     * needs seven logged days before it will say anything — so the app's single
     * most defensible metric was invisible in the one account people look at.
     *
     * The intake is deliberately set ABOVE the athlete's true maintenance by a
     * known amount. seedBody grows weight at 0.09 kg/week, which at 7700 kcal
     * per kg is a surplus of about 99 kcal/day; putting average intake at 3,150
     * therefore implies a measured maintenance near 3,050, comfortably clear of
     * the Mifflin/Katch estimate for this profile. The demo then shows the
     * measurement disagreeing with the formula, which is the whole point of it.
     */
    private function seedIntake(User $user): void
    {
        $rows = [];
        $today = Carbon::now($user->resolvedTimezone())->startOfDay();

        for ($i = 27; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);

            // Weekday-shaped variation: nobody eats an identical number daily,
            // and a flat line would make the average look fabricated.
            $swing = sin($i / 1.7) * 180 + ($date->isWeekend() ? 220 : -40);
            $calories = (int) round(3150 + $swing);

            $rows[] = [
                'user_id' => $user->id,
                'date' => $date->toDateString(),
                'calories' => $calories,
                'protein_g' => (int) round(165 + $swing / 40),
                'fat_g' => (int) round(70 + $swing / 90),
                'carb_g' => (int) round(430 + $swing / 8),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('intake_logs')->insert($rows);
    }
}
