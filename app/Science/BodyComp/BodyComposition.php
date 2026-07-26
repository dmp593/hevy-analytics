<?php

namespace App\Science\BodyComp;

class BodyComposition
{
    /** FFMI = lean mass (kg) / height^2 (m) */
    public static function ffmi(float $leanMassKg, float $heightCm): ?float
    {
        if ($leanMassKg <= 0 || $heightCm <= 0) {
            return null;
        }
        $h = $heightCm / 100;

        return round($leanMassKg / ($h * $h), 2);
    }

    /**
     * Normalized FFMI (for 1.80m individual).
     * FFMI_norm = FFMI + 6.1 * (1.80 - height_m)
     */
    public static function ffmiNormalized(float $leanMassKg, float $heightCm): ?float
    {
        $ffmi = self::ffmi($leanMassKg, $heightCm);
        if ($ffmi === null) {
            return null;
        }

        return round($ffmi + 6.1 * (1.80 - $heightCm / 100), 2);
    }

    /** Lean mass from weight and fat% */
    public static function leanMassFromFat(float $weightKg, ?float $fatPercent): ?float
    {
        if ($weightKg <= 0 || $fatPercent === null || $fatPercent < 0 || $fatPercent > 70) {
            return null;
        }

        return round($weightKg * (1 - $fatPercent / 100), 2);
    }

    /** Fat mass in kg */
    public static function fatMassKg(float $weightKg, ?float $fatPercent): ?float
    {
        if ($weightKg <= 0 || $fatPercent === null || $fatPercent < 0 || $fatPercent > 70) {
            return null;
        }

        return round($weightKg * $fatPercent / 100, 2);
    }

    /**
     * US Navy circumference method (men).
     * bodyFat% = 495 / (1.0324 − 0.19077·log10(waist−neck) + 0.15456·log10(height)) − 450
     * Requires neck_cm, waist, height_cm (all in cm).
     */
    public static function navyBodyFatMen(float $neckCm, float $abdomenCm, float $heightCm): ?float
    {
        if ($neckCm <= 0 || $abdomenCm <= 0 || $heightCm <= 0) {
            return null;
        }

        $diff = $abdomenCm - $neckCm;
        if ($diff <= 0) {
            return null;
        }

        $logDiff = log10($diff);
        $logHt = log10($heightCm);
        $denom = 1.0324 - 0.19077 * $logDiff + 0.15456 * $logHt;

        if ($denom == 0) {
            return null;
        }

        return round(495 / $denom - 450, 1);
    }

    /**
     * Boer LBM formula (used when no fat% is available).
     * men: LBM = 0.407·W + 0.267·H − 19.2
     * women: LBM = 0.252·W + 0.473·H − 48.3
     */
    public static function boerLbm(float $weightKg, float $heightCm, string $sex = 'male'): float
    {
        $isFemale = in_array(strtolower($sex), ['female', 'f', 'woman'], true);
        $w = $weightKg;
        $h = $heightCm;

        return round($isFemale
            ? 0.252 * $w + 0.473 * $h - 48.3
            : 0.407 * $w + 0.267 * $h - 19.2, 2);
    }

    /** Waist-to-height ratio (health risk > 0.5) */
    public static function waistToHeightRatio(float $waistCm, float $heightCm): ?float
    {
        if ($waistCm <= 0 || $heightCm <= 0) {
            return null;
        }

        return round($waistCm / $heightCm, 3);
    }

    /** Waist-to-hip ratio */
    public static function waistToHipRatio(float $waistCm, float $hipsCm): ?float
    {
        if ($waistCm <= 0 || $hipsCm <= 0) {
            return null;
        }

        return round($waistCm / $hipsCm, 3);
    }

    /** Symmetry index: % difference between left and right circumference */
    public static function symmetryPct(?float $left, ?float $right): ?float
    {
        if ($left === null || $right === null || $left <= 0 || $right <= 0) {
            return null;
        }

        return round(abs($left - $right) / (($left + $right) / 2) * 100, 1);
    }
}
