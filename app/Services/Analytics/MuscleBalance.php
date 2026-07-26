<?php

namespace App\Services\Analytics;

use App\Models\User;
use Illuminate\Support\Collection;

class MuscleBalance
{
    private const PUSH = ['chest', 'shoulders', 'triceps'];

    private const PULL = ['lats', 'upper_back', 'traps', 'biceps', 'forearms', 'lower_back'];

    private const QUADS = ['quadriceps'];

    private const POSTERIOR_CHAIN = ['hamstrings', 'glutes', 'lower_back'];

    private const UPPER = ['chest', 'shoulders', 'triceps', 'biceps', 'forearms', 'lats', 'upper_back', 'traps'];

    private const LOWER = ['quadriceps', 'hamstrings', 'glutes', 'calves', 'abductors', 'adductors'];

    /** Combined sets across both sides in the window below which a ratio is noise. */
    private const MIN_TOTAL_SETS = 12.0;

    public function __construct(
        private readonly User $user,
        private readonly FilterCriteria $filter,
    ) {}

    /**
     * Balance is measured in SETS, not tonnage.
     *
     * Tonnage is load x reps, so a squat set contributes several times what a
     * lateral raise set does purely because the bar is heavier. Summing it by
     * region means lower-body tonnage structurally swamps upper body and the
     * "balanced" band is unreachable no matter how someone trains. Hard sets are
     * the unit hypertrophy research actually prescribes, and the unit the rest
     * of this app already grades against MEV/MAV/MRV.
     */
    public function ratios(): array
    {
        $rows = collect((new VolumeAnalytics($this->user, $this->filter))->weeklySetsPerMuscle())
            ->keyBy('muscle');

        $perWeek = $rows->map(fn ($x) => (float) $x['per_week']);
        // Totals drive the "is there enough data" gate. Per-week values shrink
        // as the window widens, so gating on them would let the chosen date
        // range decide whether a ratio exists at all.
        $totals = $rows->map(fn ($x) => (float) $x['total_sets']);

        $group = fn (array $muscles) => [
            $this->sum($perWeek, $muscles),
            $this->sum($totals, $muscles),
        ];

        [$pushWk, $pushTotal] = $group(self::PUSH);
        [$pullWk, $pullTotal] = $group(self::PULL);
        [$quadWk, $quadTotal] = $group(self::QUADS);
        [$postWk, $postTotal] = $group(self::POSTERIOR_CHAIN);
        [$upperWk, $upperTotal] = $group(self::UPPER);
        [$lowerWk, $lowerTotal] = $group(self::LOWER);

        return [
            'push_pull' => $this->ratio($pushWk, $pullWk, $pushTotal + $pullTotal, 'Push', 'Pull'),
            'quad_posterior' => $this->ratio($quadWk, $postWk, $quadTotal + $postTotal, 'Quads', 'Posterior chain'),
            'upper_lower' => $this->ratio($upperWk, $lowerWk, $upperTotal + $lowerTotal, 'Upper', 'Lower'),
        ];
    }

    private function sum(Collection $sets, array $muscles): float
    {
        return array_sum(array_map(fn ($m) => $sets[$m] ?? 0, $muscles));
    }

    /**
     * A ratio is only meaningful once both sides carry enough sets for the
     * comparison to mean anything; below that we report it as indeterminate
     * rather than flagging a beginner's first week as "imbalanced".
     */
    private function ratio(float $a, float $b, float $totalSets, string $labelA, string $labelB): array
    {
        $enoughData = $totalSets >= self::MIN_TOTAL_SETS;
        $ratio = ($enoughData && $b > 0) ? round($a / $b, 2) : null;
        $balanced = $ratio !== null && $ratio >= 0.8 && $ratio <= 1.25;

        return [
            'label_a' => $labelA,
            'label_b' => $labelB,
            'value_a' => round($a, 1),
            'value_b' => round($b, 1),
            'unit' => 'sets/wk',
            'ratio' => $ratio,
            'balanced' => $balanced,
            'has_data' => $enoughData,
        ];
    }
}
