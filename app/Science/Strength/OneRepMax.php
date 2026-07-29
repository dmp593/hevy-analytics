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
    /** Beyond this many reps an estimate is not trustworthy enough to headline. */
    public const MAX_RELIABLE_REPS = 12;

    /**
     * Hard ceiling on the reps fed to the formulas.
     *
     * Distinct from MAX_RELIABLE_REPS, which is a judgement about trust. This is
     * a numerical guard: Brzycki's denominator (37 - reps) approaches zero, so
     * 36 reps returns ~19x the load. Clamping at the reliability threshold
     * instead would erase RPE information entirely — a 12-rep set at RPE 6 and
     * at RPE 10 would report the same e1RM.
     */
    public const MAX_FORMULA_REPS = 15;

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

        // Numerical guard only — see MAX_FORMULA_REPS. Whether the result should
        // be trusted is a separate question, answered by isReliableSet().
        $effectiveReps = min($effectiveReps, (float) self::MAX_FORMULA_REPS);

        if ($effectiveReps <= 1) {
            return round($weight, 2);
        }

        return round((self::epley($weight, $effectiveReps) + self::brzycki($weight, $effectiveReps)) / 2, 2);
    }

    /**
     * Whether an estimate built from this set is trustworthy enough to headline
     * as a personal record. RPE-derived reps-in-reserve are an inference, not a
     * measurement, so a set well short of failure does not qualify however few
     * reps were performed.
     */
    public static function isReliableSet(float $reps, ?float $rpe = null): bool
    {
        if (! self::isReliable($reps)) {
            return false;
        }

        return $rpe === null || $rpe >= 7.0;
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
}
