<?php

namespace App\Science\Strength;

use App\Science\BodyComp\BodyComposition;

/**
 * Bodyweight-adjusted strength scores so progress is comparable as weight
 * changes during a bulk or cut.
 *
 * Wilks (2020 coefficients) and DOTS. Input weights in kg.
 */
class StrengthScore
{
    /** Wilks 2020 coefficients. */
    private const WILKS2020 = [
        'male' => [47.46178854, 8.472061379, 0.07369410346, -0.001395833811, 7.07665973070743e-6, -1.20804336482315e-8],
        'female' => [-125.4255398, 13.71219419, -0.03307250631, -0.001050400051, 9.38773881462799e-6, -2.3334613884954e-8],
    ];

    /** DOTS coefficients. */
    private const DOTS = [
        'male' => [-307.75076, 24.0900756, -0.1918759221, 0.0007391293, -0.000001093],
        'female' => [-57.96288, 13.6175032, -0.1126655495, 0.0005158568, -0.0000010706],
    ];

    public static function wilks(float $liftedKg, float $bodyweightKg, string $sex = 'male'): ?float
    {
        if ($liftedKg <= 0 || $bodyweightKg <= 0) {
            return null;
        }
        $c = self::WILKS2020[self::normalizeSex($sex)];
        $x = $bodyweightKg;
        $denom = $c[0] + $c[1] * $x + $c[2] * $x ** 2 + $c[3] * $x ** 3 + $c[4] * $x ** 4 + $c[5] * $x ** 5;

        if ($denom == 0.0) {
            return null;
        }

        return round($liftedKg * 600 / $denom, 2);
    }

    public static function dots(float $liftedKg, float $bodyweightKg, string $sex = 'male'): ?float
    {
        if ($liftedKg <= 0 || $bodyweightKg <= 0) {
            return null;
        }
        $c = self::DOTS[self::normalizeSex($sex)];
        // OpenPowerlifting clamps the polynomial's domain per sex: 40-210 kg
        // for men, 40-150 kg for women (coefficients/src/dots.rs).
        $x = min(max($bodyweightKg, 40), self::normalizeSex($sex) === 'female' ? 150 : 210);
        $denom = $c[0] + $c[1] * $x + $c[2] * $x ** 2 + $c[3] * $x ** 3 + $c[4] * $x ** 4;

        if ($denom == 0.0) {
            return null;
        }

        return round($liftedKg * 500 / $denom, 2);
    }

    /** Simple relative strength: lift / bodyweight. */
    public static function relative(float $liftedKg, float $bodyweightKg): ?float
    {
        if ($bodyweightKg <= 0) {
            return null;
        }

        return round($liftedKg / $bodyweightKg, 3);
    }

    private static function normalizeSex(string $sex): string
    {
        return BodyComposition::normalizeSex($sex);
    }
}
