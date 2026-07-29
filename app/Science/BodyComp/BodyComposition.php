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
     * US Navy circumference method (women).
     * bodyFat% = 495 / (1.29579 − 0.35004·log10(waist+hip−neck) + 0.22100·log10(height)) − 450
     *
     * Unlike the men's equation this needs a hip measurement; without one the
     * estimate cannot be made and we return null rather than silently falling
     * back to the men's formula (which under-reads a typical woman by roughly
     * 12 percentage points).
     */
    public static function navyBodyFatWomen(float $neckCm, float $waistCm, float $hipCm, float $heightCm): ?float
    {
        if ($neckCm <= 0 || $waistCm <= 0 || $hipCm <= 0 || $heightCm <= 0) {
            return null;
        }

        $diff = $waistCm + $hipCm - $neckCm;
        if ($diff <= 0) {
            return null;
        }

        $denom = 1.29579 - 0.35004 * log10($diff) + 0.22100 * log10($heightCm);

        if ($denom == 0) {
            return null;
        }

        return round(495 / $denom - 450, 1);
    }

    /**
     * Navy body fat for the given sex, dispatching to the correct equation.
     * Women require a hip measurement; when it is missing this returns null so
     * callers can ask for the measurement instead of showing a wrong number.
     */
    public static function navyBodyFat(
        string $sex,
        ?float $neckCm,
        ?float $abdomenCm,
        ?float $heightCm,
        ?float $hipCm = null,
    ): ?float {
        if ($neckCm === null || $abdomenCm === null || $heightCm === null) {
            return null;
        }

        if (self::isFemale($sex)) {
            return $hipCm === null
                ? null
                : self::navyBodyFatWomen($neckCm, $abdomenCm, $hipCm, $heightCm);
        }

        return self::navyBodyFatMen($neckCm, $abdomenCm, $heightCm);
    }

    /** Whether a stored sex value denotes female. */
    public static function isFemale(?string $sex): bool
    {
        return in_array(strtolower((string) $sex), ['female', 'f', 'woman'], true);
    }

    /**
     * Boer LBM formula (used when no fat% is available).
     * men: LBM = 0.407·W + 0.267·H − 19.2
     * women: LBM = 0.252·W + 0.473·H − 48.3
     */
    public static function boerLbm(float $weightKg, float $heightCm, string $sex = 'male'): float
    {
        $isFemale = self::isFemale($sex);
        $w = $weightKg;
        $h = $heightCm;

        return round($isFemale
            ? 0.252 * $w + 0.473 * $h - 48.3
            : 0.407 * $w + 0.267 * $h - 19.2, 2);
    }

    /**
     * Relative Fat Mass (Woolcott & Bergman 2018, Scientific Reports).
     * RFM = 64 − 20·(height/waist), +12 for women. Tape-only, validated
     * against DXA — a third estimator to triangulate beside BIA and Navy.
     */
    public static function relativeFatMass(float $heightCm, float $waistCm, ?string $sex): ?float
    {
        if ($heightCm <= 0 || $waistCm <= 0) {
            return null;
        }

        $rfm = 64 - 20 * ($heightCm / $waistCm) + (self::isFemale($sex) ? 12 : 0);

        // Outside any physiological range means a mistyped measurement, not a
        // body-fat estimate worth showing.
        return ($rfm < 2 || $rfm > 65) ? null : round($rfm, 1);
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
