<?php

namespace App\Science\Volume;

/**
 * Muscle group taxonomy (matching Hevy's MuscleGroup enum) plus weekly volume
 * landmarks in "hard sets per week", per Renaissance Periodization
 * (Israetel et al.) — ADAPTED, not copied: RP tables count direct sets only,
 * this app credits secondaries 0.5, so several rows deliberately differ and
 * untabled muscles carry conservative in-house values.
 * MV (maintenance), MEV (minimum effective), MAV
 * (maximum adaptive), MRV (maximum recoverable).
 */
class MuscleLandmarks
{
    /** Hevy muscle groups that meaningfully receive resistance volume. */
    public const GROUPS = [
        'chest', 'shoulders', 'triceps', 'biceps', 'forearms', 'lats',
        'upper_back', 'traps', 'lower_back', 'abdominals', 'quadriceps',
        'hamstrings', 'glutes', 'calves', 'abductors', 'adductors', 'neck',
    ];

    /** [mv, mev, mav, mrv] weekly hard-set landmarks. */
    public const LANDMARKS = [
        'chest' => [8, 10, 16, 22],
        'shoulders' => [8, 8, 19, 26],
        'triceps' => [6, 8, 14, 18],
        'biceps' => [6, 8, 16, 20],
        'forearms' => [4, 6, 12, 16],
        'lats' => [8, 10, 18, 22],
        'upper_back' => [8, 10, 18, 25],
        'traps' => [4, 6, 14, 20],
        'lower_back' => [4, 6, 12, 16],
        'abdominals' => [0, 6, 16, 25],
        'quadriceps' => [8, 8, 18, 20],
        'hamstrings' => [6, 6, 16, 20],
        'glutes' => [0, 4, 12, 16],
        'calves' => [6, 8, 16, 20],
        'abductors' => [0, 4, 12, 16],
        'adductors' => [0, 4, 12, 16],
        'neck' => [0, 4, 12, 16],
    ];

    public const DEFAULT = [6, 8, 16, 22];

    public static function for(string $muscle): array
    {
        [$mv, $mev, $mav, $mrv] = self::LANDMARKS[$muscle] ?? self::DEFAULT;

        return ['mv' => $mv, 'mev' => $mev, 'mav' => $mav, 'mrv' => $mrv];
    }

    /**
     * Classify weekly hard-set count against landmarks.
     * Returns one of: below_maintenance, maintenance, growth, optimal, junk.
     */
    public static function classify(string $muscle, float $weeklySets): string
    {
        $l = self::for($muscle);

        return match (true) {
            $weeklySets < $l['mv'] => 'below_maintenance',
            $weeklySets < $l['mev'] => 'maintenance',
            $weeklySets <= $l['mav'] => 'optimal',
            $weeklySets <= $l['mrv'] => 'growth',
            default => 'junk',
        };
    }
}
