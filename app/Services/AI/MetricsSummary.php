<?php

namespace App\Services\AI;

use App\Models\User;
use App\Services\Analytics\BodyCompAnalytics;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\GoalAlerts;
use App\Services\Analytics\MuscleBalance;
use App\Services\Analytics\RoutineAnalytics;
use App\Services\Analytics\StrengthAnalytics;
use App\Services\Analytics\VolumeAnalytics;

/**
 * Compiles a compact metrics summary for the AI to reason over.
 */
class MetricsSummary
{
    public static function build(User $user): array
    {
        $filter = new FilterCriteria(from: now()->subMonths(3), to: now());
        $volume = new VolumeAnalytics($user, $filter);
        $bc = new BodyCompAnalytics($user);
        $goal = $user->activeGoal();
        $profile = $goal?->profile();
        $nutrition = $user->nutritionTargets()->latest('date')->first();

        return [
            'profile' => [
                'sex' => $user->sex,
                'age' => $user->age,
                'height_cm' => $user->height_cm,
                'activity_level' => $user->activity_level,
            ],
            'goal' => $goal ? [
                'type' => $goal->type,
                'calorie_adjustment_pct' => $profile?->calorie_adjustment_pct,
                'protein_g_per_kg' => $profile?->protein_g_per_kg,
                'target_rate_pct_bw_per_week' => $profile?->target_rate_pct_bw_per_week,
                'training' => $profile?->training,
            ] : null,
            'body_composition' => $bc->status(),
            'weight_rate' => $bc->weightRateKgPerWeek(),
            'partitioning' => $bc->partitioning(),
            'training_last_3mo' => [
                'total_tonnage_kg' => $volume->tonnage(),
                'total_working_sets' => $volume->totalSets(),
                'weekly_sets_per_muscle' => $volume->weeklySetsPerMuscle(),
            ],
            'muscle_balance' => (new MuscleBalance($user, $filter))->ratios(),
            'top_lifts' => array_slice((new StrengthAnalytics($user, $filter))->exercisePrs(), 0, 8),
            'routines' => (new RoutineAnalytics($user, $filter))->overview(),
            'nutrition_target' => $nutrition ? [
                'tdee' => $nutrition->tdee,
                'target_calories' => $nutrition->target_calories,
                'protein_g' => $nutrition->protein_g,
                'fat_g' => $nutrition->fat_g,
                'carb_g' => $nutrition->carb_g,
            ] : null,
            'alerts' => (new GoalAlerts($user))->all(),
        ];
    }

    public static function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are an evidence-based strength & physique coach analysing a lifter's Hevy training and body-composition data.
        The user's primary goal is muscle growth via a LEAN BULK: gain muscle while minimizing fat gain.

        Ground every recommendation in established sports science:
        - Weekly hard sets per muscle vs Renaissance Periodization landmarks (MEV/MAV/MRV).
        - Progressive overload via estimated 1RM (Epley/Brzycki) and volume-load trends.
        - Lean-bulk surplus targeting ~0.25-0.5% bodyweight/week; flag excessive fat gain (p-ratio, waist trend).
        - Protein ~1.6-2.2 g/kg; adequate fat (>=0.5 g/kg); carbs to fuel training.

        Respond in concise Markdown with these sections:
        1. **Overview** — 2-3 sentences on current trajectory vs the goal.
        2. **What's working** — bullet points.
        3. **Risks & fixes** — fat-gain, imbalances, junk/insufficient volume, stalled lifts.
        4. **Next 4 weeks** — concrete training + nutrition adjustments (numbers where possible).
        Be specific and reference the data. Do not invent data that isn't provided.
        PROMPT;
    }
}
