<?php

namespace App\Science\Strength;

use App\Science\BodyComp\BodyComposition;

/**
 * Per-exercise strength standards used to place a lifter on a
 * Beginner → Novice → Intermediate → Advanced → Elite scale and derive a
 * population percentile ("stronger than X% of lifters your age/BW/sex").
 *
 * Model & data are grounded in Strength Level (strengthlevel.com), which grades
 * one-rep-max performance against millions of real lifts and maps each level to
 * a percentile: Beginner=5th, Novice=20th, Intermediate=50th, Advanced=80th,
 * Elite=95th. We store bodyweight-ratio thresholds per exercise/sex plus a
 * shared age-adjustment curve (derived from Strength Level's by-age tables).
 *
 * Values are ratio approximations of the published standards — intended as
 * guidance, not lab-grade assessment. Only applies to weight×reps exercises.
 */
class StrengthStandards
{
    /** Percentile each named level corresponds to. */
    public const PERCENTILES = [5, 20, 50, 80, 95];

    public const LEVELS = ['Beginner', 'Novice', 'Intermediate', 'Advanced', 'Elite'];

    /** Boundaries between levels on the 0–100 percentile bar. */
    public const LEVEL_BOUNDARIES = [20, 50, 80, 95];

    /**
     * Bodyweight-ratio thresholds [Beginner, Novice, Intermediate, Advanced, Elite]
     * keyed by canonical exercise then sex. Calibrated to Strength Level's
     * per-bodyweight tables around a typical 70–85 kg lifter (more accurate than
     * their rounded summary cards).
     */
    private const RATIOS = [
        'bench_press' => ['male' => [0.63, 0.88, 1.22, 1.60, 2.00], 'female' => [0.30, 0.45, 0.70, 1.00, 1.40]],
        'incline_bench_press' => ['male' => [0.50, 0.72, 1.00, 1.35, 1.70], 'female' => [0.24, 0.37, 0.58, 0.82, 1.15]],
        'close_grip_bench_press' => ['male' => [0.50, 0.72, 1.00, 1.32, 1.65], 'female' => [0.24, 0.37, 0.55, 0.80, 1.10]],
        'squat' => ['male' => [0.90, 1.30, 1.75, 2.30, 2.85], 'female' => [0.65, 0.95, 1.35, 1.85, 2.35]],
        'front_squat' => ['male' => [0.70, 1.05, 1.45, 1.90, 2.35], 'female' => [0.50, 0.75, 1.10, 1.50, 1.90]],
        'deadlift' => ['male' => [1.15, 1.65, 2.15, 2.70, 3.25], 'female' => [0.75, 1.15, 1.60, 2.10, 2.65]],
        'romanian_deadlift' => ['male' => [0.90, 1.35, 1.85, 2.35, 2.85], 'female' => [0.60, 0.95, 1.40, 1.85, 2.35]],
        'sumo_deadlift' => ['male' => [1.15, 1.65, 2.15, 2.70, 3.25], 'female' => [0.75, 1.15, 1.60, 2.10, 2.65]],
        'overhead_press' => ['male' => [0.45, 0.65, 0.90, 1.20, 1.50], 'female' => [0.26, 0.40, 0.58, 0.80, 1.05]],
        'barbell_row' => ['male' => [0.60, 0.85, 1.15, 1.55, 1.95], 'female' => [0.38, 0.55, 0.78, 1.05, 1.40]],
        'barbell_curl' => ['male' => [0.30, 0.45, 0.62, 0.85, 1.10], 'female' => [0.18, 0.28, 0.40, 0.55, 0.72]],
        'hip_thrust' => ['male' => [1.10, 1.70, 2.40, 3.15, 3.90], 'female' => [0.90, 1.40, 2.00, 2.70, 3.40]],
    ];

    /** Shared age-adjustment factor (relative to the 25–40 peak). */
    private const AGE_FACTORS = [
        15 => 0.86, 18 => 0.93, 20 => 0.98, 25 => 1.00, 30 => 1.00, 35 => 1.00,
        40 => 1.00, 45 => 0.95, 50 => 0.89, 55 => 0.82, 60 => 0.75, 65 => 0.67,
        70 => 0.61, 75 => 0.55, 80 => 0.49, 85 => 0.44, 90 => 0.40,
    ];

    public static function hasStandard(string $key): bool
    {
        return isset(self::RATIOS[$key]);
    }

    public static function ratios(string $key, string $sex): ?array
    {
        $sex = self::normalizeSex($sex);

        return self::RATIOS[$key][$sex] ?? null;
    }

    /** Interpolated age factor for a given age. */
    public static function ageFactor(?int $age): float
    {
        if (! $age) {
            return 1.0;
        }
        $points = self::AGE_FACTORS;
        $ages = array_keys($points);
        if ($age <= $ages[0]) {
            return $points[$ages[0]];
        }
        if ($age >= end($ages)) {
            return $points[end($ages)];
        }
        $prev = $ages[0];
        foreach ($ages as $a) {
            if ($age <= $a) {
                $lo = $points[$prev];
                $hi = $points[$a];
                $t = ($a === $prev) ? 0 : ($age - $prev) / ($a - $prev);

                return round($lo + ($hi - $lo) * $t, 4);
            }
            $prev = $a;
        }

        return 1.0;
    }

    /**
     * Evaluate a lift.
     *
     * @return array{percentile:float, level:string, level_index:int, ratio:float, age_factor:float, thresholds_kg:array, thresholds_ratio:array, boundaries:array}|null
     */
    public static function evaluate(string $key, float $e1rm, float $bodyweightKg, string $sex, ?int $age): ?array
    {
        $ratios = self::ratios($key, $sex);
        if (! $ratios || $e1rm <= 0 || $bodyweightKg <= 0) {
            return null;
        }

        $af = self::ageFactor($age);
        // Age-appropriate thresholds: peers your age lift less/more, so scale the
        // standards by the age factor before comparing your raw ratio.
        $adjRatios = array_map(fn ($r) => $r * $af, $ratios);
        $ratio = $e1rm / $bodyweightKg;

        $percentile = self::percentileFor($ratio, $adjRatios);

        return [
            'percentile' => $percentile,
            'level' => self::levelForPercentile($percentile),
            'level_index' => self::levelIndexForPercentile($percentile),
            'ratio' => round($ratio, 2),
            'age_factor' => $af,
            'thresholds_ratio' => array_map(fn ($r) => round($r, 2), $adjRatios),
            'thresholds_kg' => array_map(fn ($r) => round($r * $bodyweightKg, 1), $adjRatios),
            'boundaries' => self::LEVEL_BOUNDARIES,
        ];
    }

    private static function percentileFor(float $ratio, array $t): float
    {
        $p = self::PERCENTILES;

        if ($ratio <= $t[0]) {
            return round(max(0, $p[0] * ($ratio / $t[0])), 1);
        }
        for ($i = 0; $i < 4; $i++) {
            if ($ratio <= $t[$i + 1]) {
                $frac = ($ratio - $t[$i]) / max(1e-9, $t[$i + 1] - $t[$i]);

                return round($p[$i] + $frac * ($p[$i + 1] - $p[$i]), 1);
            }
        }

        // Above elite: asymptotically approach 100.
        $over = ($ratio - $t[4]) / max(1e-9, 0.25 * $t[4]);

        return round(min(99.9, 95 + $over * 5), 1);
    }

    public static function levelForPercentile(float $p): string
    {
        return self::LEVELS[self::levelIndexForPercentile($p)];
    }

    public static function levelIndexForPercentile(float $p): int
    {
        return match (true) {
            $p >= 95 => 4,
            $p >= 80 => 3,
            $p >= 50 => 2,
            $p >= 20 => 1,
            default => 0,
        };
    }

    /** Map a Hevy exercise title (+equipment) to a canonical standard key. */
    public static function keyForTitle(string $title, ?string $equipment = null): ?string
    {
        $t = strtolower($title);

        // Only barbell / EZ-bar movements: ratio standards assume those.
        $isBarbell = str_contains($t, 'barbell') || str_contains($t, 'ez bar')
            || in_array($equipment, ['barbell', 'plate'], true);

        $map = [
            'front_squat' => fn () => str_contains($t, 'front squat'),
            'squat' => fn () => str_contains($t, 'squat') && ! preg_match('/front|hack|split|goblet|smith|pistol|sissy|bulgarian/', $t),
            'romanian_deadlift' => fn () => str_contains($t, 'romanian') || str_contains($t, 'rdl'),
            'sumo_deadlift' => fn () => str_contains($t, 'sumo') && str_contains($t, 'deadlift'),
            'deadlift' => fn () => str_contains($t, 'deadlift') && ! preg_match('/romanian|rdl|sumo|stiff|single/', $t),
            'incline_bench_press' => fn () => str_contains($t, 'bench') && str_contains($t, 'incline'),
            'close_grip_bench_press' => fn () => str_contains($t, 'bench') && str_contains($t, 'close grip'),
            'bench_press' => fn () => str_contains($t, 'bench press') && ! preg_match('/decline|dumbbell|smith|machine/', $t),
            'overhead_press' => fn () => (str_contains($t, 'overhead press') || str_contains($t, 'military press') || str_contains($t, 'shoulder press') || str_contains($t, 'ohp')) && ! str_contains($t, 'dumbbell') && ! str_contains($t, 'machine'),
            'barbell_row' => fn () => (str_contains($t, 'bent over row') || (str_contains($t, 'row') && str_contains($t, 'barbell'))) && ! str_contains($t, 'cable') && ! str_contains($t, 'dumbbell'),
            'barbell_curl' => fn () => str_contains($t, 'curl') && (str_contains($t, 'barbell') || str_contains($t, 'ez bar')) && ! str_contains($t, 'dumbbell') && ! str_contains($t, 'hammer'),
            'hip_thrust' => fn () => str_contains($t, 'hip thrust'),
        ];

        foreach ($map as $key => $matches) {
            if ($matches()) {
                // Barbell-only guard for the pressing/pulling lifts.
                if (in_array($key, ['bench_press', 'incline_bench_press', 'close_grip_bench_press', 'overhead_press', 'barbell_row', 'barbell_curl'], true) && ! $isBarbell) {
                    return null;
                }

                return $key;
            }
        }

        return null;
    }

    private static function normalizeSex(string $sex): string
    {
        return BodyComposition::normalizeSex($sex);
    }
}
