<?php

namespace App\Science\Nutrition;

class Macros
{
    public const KCAL_PER_G_PROTEIN = 4;

    public const KCAL_PER_G_CARB = 4;

    public const KCAL_PER_G_FAT = 9;

    /** kcal per kg of body-weight change (approx. energy of mixed tissue). */
    public const KCAL_PER_KG = 7700;

    /**
     * TDEE = BMR × activity factor (PAL). Optional additive training energy
     * (kcal/day) can be layered on top when computed from session METs.
     */
    public static function tdee(float $bmr, float $activityFactor, float $trainingKcalPerDay = 0): float
    {
        return round($bmr * $activityFactor + $trainingKcalPerDay, 1);
    }

    /**
     * Target calories given a percentage adjustment on maintenance (TDEE).
     * e.g. +10 for a lean bulk surplus, −20 for a cut.
     */
    public static function targetCalories(float $tdee, float $adjustmentPct): float
    {
        return round($tdee * (1 + $adjustmentPct / 100), 0);
    }

    /**
     * Compute protein / fat / carb grams.
     * Protein and fat are anchored to bodyweight (g/kg); carbs fill the rest.
     * Fat has a hormonal floor (~0.5 g/kg already low); carbs floored at 0.
     *
     * @return array{protein_g: float, fat_g: float, carb_g: float, protein_kcal: float, fat_kcal: float, carb_kcal: float}
     */
    public static function split(float $targetCalories, float $bodyweightKg, float $proteinPerKg, float $fatPerKg): array
    {
        $proteinG = round($proteinPerKg * $bodyweightKg, 0);
        // The hormonal floor the docblock promises, enforced: overrides
        // below 0.5 g/kg are clamped rather than silently honoured.
        $fatG = round(max($fatPerKg, 0.5) * $bodyweightKg, 0);

        $proteinKcal = $proteinG * self::KCAL_PER_G_PROTEIN;
        $fatKcal = $fatG * self::KCAL_PER_G_FAT;

        $remaining = $targetCalories - $proteinKcal - $fatKcal;
        $carbG = max(0, round($remaining / self::KCAL_PER_G_CARB, 0));

        return [
            'protein_g' => $proteinG,
            'fat_g' => $fatG,
            'carb_g' => $carbG,
            'protein_kcal' => $proteinKcal,
            'fat_kcal' => $fatKcal,
            'carb_kcal' => $carbG * self::KCAL_PER_G_CARB,
        ];
    }

    /** Recommended daily fiber (~14 g per 1000 kcal). */
    public static function fiberG(float $calories): float
    {
        return round($calories / 1000 * 14, 0);
    }

    /**
     * Expected weekly body-weight change (kg) from a daily calorie surplus/deficit.
     */
    public static function weeklyWeightChangeKg(float $dailyCalorieDelta): float
    {
        return round($dailyCalorieDelta * 7 / self::KCAL_PER_KG, 3);
    }
}
