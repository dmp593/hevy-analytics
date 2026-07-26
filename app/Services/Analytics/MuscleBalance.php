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

    /** Combined weekly sets across both sides below which a ratio is noise. */
    private const MIN_WEEKLY_SETS = 4.0;

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
        $sets = collect((new VolumeAnalytics($this->user, $this->filter))->weeklySetsPerMuscle())
            ->keyBy('muscle')
            ->map(fn ($x) => (float) $x['per_week']);

        $push = $this->sum($sets, self::PUSH);
        $pull = $this->sum($sets, self::PULL);
        $quads = $this->sum($sets, self::QUADS);
        $posterior = $this->sum($sets, self::POSTERIOR_CHAIN);
        $upper = $this->sum($sets, self::UPPER);
        $lower = $this->sum($sets, self::LOWER);

        return [
            'push_pull' => $this->ratio($push, $pull, 'Push', 'Pull'),
            'quad_posterior' => $this->ratio($quads, $posterior, 'Quads', 'Posterior chain'),
            'upper_lower' => $this->ratio($upper, $lower, 'Upper', 'Lower'),
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
    private function ratio(float $a, float $b, string $labelA, string $labelB): array
    {
        $enoughData = $a + $b >= self::MIN_WEEKLY_SETS;
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
