<?php

namespace App\Services\Analytics;

use App\Models\NutritionTarget;
use App\Models\User;
use App\Science\BodyComp\BodyComposition;
use App\Science\Nutrition\Bmr;
use App\Science\Nutrition\Macros;
use Illuminate\Support\Carbon;

class NutritionService
{
    public function __construct(private readonly User $user) {}

    /**
     * Compute today's recommended targets from current body composition and the
     * active goal, applying adaptive-TDEE correction from the observed weight
     * trend when intake data is available. Persists a NutritionTarget row.
     */
    public function computeTargets(?Carbon $date = null): ?NutritionTarget
    {
        $date ??= Carbon::today();
        $goal = $this->user->activeGoal();
        if (! $goal) {
            return null;
        }
        $profile = $goal->profile();

        $bc = new BodyCompAnalytics($this->user);
        // One snapshot: status() runs a batch of queries per call, and three
        // calls here were three identical batches.
        $status = $bc->status();
        $weight = $status['weight_kg'];
        if (! $weight || ! $this->user->height_cm || ! $this->user->age) {
            return null;
        }

        $fat = $status['fat_percent'];
        $lean = $status['lean_mass_kg']
            ?? BodyComposition::boerLbm($weight, $this->user->height_cm, $this->user->sex ?? 'male');

        // Prefer Katch-McArdle when body-fat is known (better for lean trainees).
        if ($fat !== null && $lean) {
            $bmr = Bmr::katchMcArdle($lean);
            $formula = 'katch_mcardle';
        } else {
            $bmr = Bmr::mifflinStJeor($weight, $this->user->height_cm, $this->user->age, $this->user->sex ?? 'male');
            $formula = 'mifflin_st_jeor';
        }

        $formulaTdee = Macros::tdee($bmr, $this->user->activity_level ?? 1.55);
        $tdee = $formulaTdee;

        // Adaptive correction: if we can estimate real maintenance from intake +
        // weight change, blend it toward the formula estimate. The adaptive
        // figure earns weight with data: at the 7-day minimum it contributes
        // 35%, at a full 28 logged days it is capped at 80% — observed intake
        // should eventually dominate a population formula, but never silence it,
        // because logging errors are systematic in a way formulas are not.
        //
        // The blend overwrites $tdee, so the formula figure has to be kept
        // separately or it is gone: the stored 'tdee' is then a hybrid wearing a
        // formula's name, and neither half can be recovered to explain the
        // difference to the athlete.
        $adaptiveData = $this->adaptiveData();
        $adaptive = $adaptiveData['maintenance'] ?? null;
        $adaptiveWeight = null;
        if ($adaptive !== null) {
            $adaptiveWeight = min(0.8, 0.2 + 0.6 * min(1, $adaptiveData['logged_days'] / 28));
            $tdee = round($formulaTdee * (1 - $adaptiveWeight) + $adaptive * $adaptiveWeight, 1);
        }

        $targetCalories = Macros::targetCalories($tdee, $profile->calorie_adjustment_pct);
        $split = Macros::split($targetCalories, $weight, $profile->protein_g_per_kg, $profile->fat_g_per_kg);

        $attributes = [
            'goal_type' => $goal->type,
            'weight_kg' => $weight,
            'lean_mass_kg' => $lean,
            'bmr' => $bmr,
            'bmr_formula' => $formula,
            'tdee' => $tdee,
            'target_calories' => $targetCalories,
            'protein_g' => $split['protein_g'],
            'fat_g' => $split['fat_g'],
            'carb_g' => $split['carb_g'],
            'basis' => [
                'activity_level' => $this->user->activity_level,
                'adjustment_pct' => $profile->calorie_adjustment_pct,
                'protein_g_per_kg' => $profile->protein_g_per_kg,
                'fat_g_per_kg' => $profile->fat_g_per_kg,
                'fiber_g' => Macros::fiberG($targetCalories),
                'adaptive_maintenance' => $adaptive,
                'adaptive_weight' => $adaptiveWeight,
                'adaptive_logged_days' => $adaptiveData['logged_days'] ?? null,
                'formula_tdee' => $formulaTdee,
                'target_rate_pct_bw_per_week' => $profile->target_rate_pct_bw_per_week,
            ],
        ];

        $target = $this->user->nutritionTargets()->whereDate('date', $date)->first()
            ?? $this->user->nutritionTargets()->make(['date' => $date->toDateString()]);
        $target->fill($attributes)->save();

        return $target;
    }

    /**
     * Estimate true maintenance calories from logged intake and observed weight
     * change: maintenance = avgIntake − (weeklyΔkg × 7700 / 7).
     */
    public function adaptiveMaintenance(int $days = 28): ?float
    {
        return $this->adaptiveData($days)['maintenance'] ?? null;
    }

    /**
     * The adaptive-maintenance estimate together with how much data backs it,
     * so callers can weight the number by the evidence behind it.
     *
     * @return array{maintenance: float, logged_days: int}|null
     */
    public function adaptiveData(int $days = 28): ?array
    {
        $from = Carbon::now()->subDays($days);
        $logs = $this->user->intakeLogs()
            ->where('date', '>=', $from)
            ->whereNotNull('calories')
            ->orderBy('date')
            ->get();

        if ($logs->count() < 7) {
            return null;
        }

        $avgIntake = $logs->avg('calories');

        // Weight change over the same window (prefer intake-log weights, else body measurements).
        $bc = new BodyCompAnalytics($this->user);
        $rate = $bc->weightRateKgPerWeek($days);
        if (! $rate) {
            return null;
        }

        $dailyDelta = $rate['kg_per_week'] * Macros::KCAL_PER_KG / 7;

        return [
            'maintenance' => round($avgIntake - $dailyDelta, 0),
            'logged_days' => $logs->count(),
        ];
    }

    /** Adherence: logged intake vs target over recent days. */
    public function adherence(int $days = 14): array
    {
        $from = Carbon::now()->subDays($days);
        $logs = $this->user->intakeLogs()->where('date', '>=', $from)->orderBy('date')->get();
        $targets = $this->user->nutritionTargets()->where('date', '>=', $from)->get()->keyBy(fn ($t) => $t->date->toDateString());

        return $logs->map(function ($log) use ($targets) {
            $t = $targets->get($log->date->toDateString());

            return [
                'date' => $log->date->toDateString(),
                'calories' => $log->calories,
                'target_calories' => $t?->target_calories,
                'protein_g' => $log->protein_g,
                'target_protein_g' => $t?->protein_g,
                'weight_kg' => $log->weight_kg,
            ];
        })->all();
    }
}
