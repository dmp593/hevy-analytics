<?php

namespace App\Science\Goals;

use App\Models\Goal;

/**
 * Preset definitions for each goal type. A Goal model can override any
 * individual parameter; where null the preset default is used.
 *
 * @property-read string $type
 * @property-read float  $calorieAdjustmentPct
 * @property-read float  $proteinGPerKg
 * @property-read float  $fatGPerKg
 * @property-read float  $targetRatePctBwPerWeek
 * @property-read array  $training  {string repRange, int setsMin, int setsMax, float riRMin, float riRMax}
 */
/*
 * Preset surpluses are DERIVED from their own target rates by the app's own
 * arithmetic (rate %BW/wk ≈ adj% × 0.03, since TDEE ≈ 33 kcal/kg and
 * 7700 kcal ≈ 1 kg): a +0.35%BW/wk lean bulk needs ~+12%, not the +7.5%
 * the audit found could never reach it. Alerts grade against these rates,
 * so preset and target must be reachable from each other.
 */
class GoalProfile
{
    private const PRESETS = [
        'lean_bulk' => [
            'calorie_adjustment_pct' => 12.0,
            'protein_g_per_kg' => 2.0,
            'fat_g_per_kg' => 0.8,
            'target_rate_pct_bw_per_week' => 0.35,
            'training' => ['rep_range' => '6-12', 'sets_min' => 10, 'sets_max' => 20, 'rir_min' => 1, 'rir_max' => 3, 'name' => 'Hypertrophy'],
        ],
        'hypertrophy' => [
            'calorie_adjustment_pct' => 5.0,
            'protein_g_per_kg' => 2.0,
            'fat_g_per_kg' => 0.8,
            'target_rate_pct_bw_per_week' => 0.15,
            'training' => ['rep_range' => '6-15', 'sets_min' => 12, 'sets_max' => 20, 'rir_min' => 0, 'rir_max' => 3, 'name' => 'Hypertrophy'],
        ],
        'aggressive_bulk' => [
            'calorie_adjustment_pct' => 25.0,
            'protein_g_per_kg' => 1.8,
            'fat_g_per_kg' => 0.9,
            'target_rate_pct_bw_per_week' => 0.75,
            'training' => ['rep_range' => '6-12', 'sets_min' => 10, 'sets_max' => 20, 'rir_min' => 1, 'rir_max' => 3, 'name' => 'Hypertrophy'],
        ],
        'recomposition' => [
            'calorie_adjustment_pct' => 0.0,
            'protein_g_per_kg' => 2.2,
            'fat_g_per_kg' => 0.7,
            'target_rate_pct_bw_per_week' => 0.0,
            'training' => ['rep_range' => '6-15', 'sets_min' => 12, 'sets_max' => 18, 'rir_min' => 1, 'rir_max' => 3, 'name' => 'Hypertrophy'],
        ],
        'cut' => [
            'calorie_adjustment_pct' => -23.0,
            'protein_g_per_kg' => 2.7,
            'fat_g_per_kg' => 0.6,
            'target_rate_pct_bw_per_week' => -0.7,
            'training' => ['rep_range' => '6-12', 'sets_min' => 8, 'sets_max' => 14, 'rir_min' => 1, 'rir_max' => 3, 'name' => 'Strength-maintenance'],
        ],
        'strength' => [
            'calorie_adjustment_pct' => 3.5,
            'protein_g_per_kg' => 1.8,
            'fat_g_per_kg' => 0.8,
            'target_rate_pct_bw_per_week' => 0.1,
            'training' => ['rep_range' => '1-6', 'sets_min' => 6, 'sets_max' => 12, 'rir_min' => 1, 'rir_max' => 3, 'name' => 'Strength'],
        ],
    ];

    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function for(Goal $goal): self
    {
        $preset = self::PRESETS[$goal->type] ?? self::PRESETS['lean_bulk'];

        return new self([
            'type' => $goal->type,
            'calorie_adjustment_pct' => $goal->calorie_adjustment_pct ?? $preset['calorie_adjustment_pct'],
            'protein_g_per_kg' => $goal->protein_g_per_kg ?? $preset['protein_g_per_kg'],
            'fat_g_per_kg' => $goal->fat_g_per_kg ?? $preset['fat_g_per_kg'],
            'target_rate_pct_bw_per_week' => $goal->target_rate_pct_bw_per_week ?? $preset['target_rate_pct_bw_per_week'],
            'training' => $goal->training ?? $preset['training'],
        ]);
    }

    /** Preset defaults without any Goal model. */
    public static function preset(string $type): self
    {
        $preset = self::PRESETS[$type] ?? self::PRESETS['lean_bulk'];

        return new self(['type' => $type, ...$preset]);
    }

    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
