<?php

namespace App\Science\Nutrition;

use App\Science\BodyComp\BodyComposition;

class Bmr
{
    /**
     * Mifflin-St Jeor (1990) — default general-population estimate.
     * P = 10·kg + 6.25·cm − 5·age + s (s = +5 male, −161 female)
     */
    public static function mifflinStJeor(float $weightKg, float $heightCm, int $age, string $sex): float
    {
        $s = self::isFemale($sex) ? -161 : 5;

        return round(10 * $weightKg + 6.25 * $heightCm - 5 * $age + $s, 1);
    }

    /**
     * Katch-McArdle / Cunningham — uses lean body mass, best for lean trainees
     * where body composition is known. P = 370 + 21.6·LBM
     */
    public static function katchMcArdle(float $leanMassKg): float
    {
        return round(370 + 21.6 * $leanMassKg, 1);
    }

    private static function isFemale(string $sex): bool
    {
        return BodyComposition::isFemale($sex);
    }
}
