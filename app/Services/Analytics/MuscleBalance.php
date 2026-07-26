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

    public function __construct(
        private readonly User $user,
        private readonly FilterCriteria $filter,
    ) {}

    public function ratios(): array
    {
        $volume = collect((new VolumeAnalytics($this->user, $this->filter))->volumePerMuscle())
            ->keyBy('muscle')
            ->map(fn ($x) => $x['tonnage']);

        $push = $this->sum($volume, self::PUSH);
        $pull = $this->sum($volume, self::PULL);
        $quads = $this->sum($volume, self::QUADS);
        $posterior = $this->sum($volume, self::POSTERIOR_CHAIN);
        $upper = $this->sum($volume, self::UPPER);
        $lower = $this->sum($volume, self::LOWER);

        return [
            'push_pull' => $this->ratio($push, $pull, 'Push', 'Pull'),
            'quad_posterior' => $this->ratio($quads, $posterior, 'Quads', 'Posterior chain'),
            'upper_lower' => $this->ratio($upper, $lower, 'Upper', 'Lower'),
        ];
    }

    private function sum(Collection $volume, array $muscles): float
    {
        return array_sum(array_map(fn ($m) => $volume[$m] ?? 0, $muscles));
    }

    private function ratio(float $a, float $b, string $labelA, string $labelB): array
    {
        $ratio = $b > 0 ? round($a / $b, 2) : null;
        $balanced = $ratio !== null && $ratio >= 0.8 && $ratio <= 1.25;

        return [
            'label_a' => $labelA,
            'label_b' => $labelB,
            'value_a' => round($a, 0),
            'value_b' => round($b, 0),
            'ratio' => $ratio,
            'balanced' => $balanced,
        ];
    }
}
