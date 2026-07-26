<?php

namespace App\Science\Strength;

/**
 * Estimated one-rep max (e1RM) formulas.
 *
 * Sources: Epley (1985), Brzycki (1998); NSCA Essentials of Strength Training.
 * Note: submaximal prediction diverges beyond ~10 reps, so callers should
 * prefer sets with reps <= self::MAX_RELIABLE_REPS.
 */
class OneRepMax
{
    public const MAX_RELIABLE_REPS = 12;

    /** Epley: w * (1 + reps/30). */
    public static function epley(float $weight, float $reps): float
    {
        if ($reps <= 1) {
            return $weight;
        }

        return $weight * (1 + $reps / 30);
    }

    /** Brzycki: w * 36 / (37 - reps). */
    public static function brzycki(float $weight, float $reps): float
    {
        if ($reps <= 1) {
            return $weight;
        }
        if ($reps >= 37) {
            return $weight; // formula breaks down / negative denominator
        }

        return $weight * 36 / (37 - $reps);
    }

    /**
     * Average of Epley and Brzycki — the two most widely used formulas.
     * Returns null for non-positive input.
     */
    public static function estimate(?float $weight, ?float $reps, ?float $rpe = null): ?float
    {
        if (! $weight || $weight <= 0 || ! $reps || $reps <= 0) {
            return null;
        }

        // RIR/RPE adjustment: sets not taken to failure under-represent the true
        // max, so treat "effective reps" = reps + reps-in-reserve (RIR = 10 - RPE).
        $effectiveReps = $reps;
        if ($rpe !== null && $rpe > 0 && $rpe <= 10) {
            $rir = max(0.0, 10 - $rpe);
            $effectiveReps = $reps + $rir;
        }

        if ($effectiveReps <= 1) {
            return round($weight, 2);
        }

        return round((self::epley($weight, $effectiveReps) + self::brzycki($weight, $effectiveReps)) / 2, 2);
    }

    /** Whether an estimate from this rep count is considered reliable. */
    public static function isReliable(float $reps): bool
    {
        return $reps <= self::MAX_RELIABLE_REPS;
    }

    /**
     * Load (kg) to use for a target rep count given an e1RM (inverse Epley).
     */
    public static function loadForReps(float $oneRepMax, float $reps): float
    {
        if ($reps <= 1) {
            return round($oneRepMax, 2);
        }

        return round($oneRepMax / (1 + $reps / 30), 2);
    }

    /** Percentage of 1RM expected for a given rep count (Epley-derived). */
    public static function percentOfMax(float $reps): float
    {
        if ($reps <= 1) {
            return 1.0;
        }

        return round(1 / (1 + $reps / 30), 4);
    }
}
