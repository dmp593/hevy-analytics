<?php

namespace App\Services\Analytics;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Progressive overload per muscle: the set-weighted mean of each lift's
 * e1RM slope (as % of the lift's own e1RM per week) across eight weeks.
 *
 * The transparent version of a "progressive overload index": no opaque
 * composite — the number is a weighted average of slopes the performance
 * page already shows per lift, and the weighting (hard sets) is stated.
 * A muscle is flat inside the same ±0.35%/week band a single lift is
 * (StrengthAnalytics::FLAT_SLOPE_FRACTION × 7), so the two pages agree.
 */
class MuscleOverload
{
    private const WEEKS = 8;

    public function __construct(private readonly User $user) {}

    /**
     * @return array<int, array{muscle: string, pct_per_week: float, direction: string, lifts: int, sets: int}>
     */
    public function perMuscle(): array
    {
        $now = Carbon::now($this->user->resolvedTimezone());
        $filter = new FilterCriteria(
            from: $now->copy()->subWeeks(self::WEEKS)->startOfDay(),
            to: $now->copy()->endOfDay(),
        );

        $board = (new StrengthAnalytics($this->user, $filter))->exerciseStatusBoard();

        $byMuscle = [];
        foreach ($board as $row) {
            if (! $row['muscle']) {
                continue;
            }
            $muscle = $row['muscle'];
            $byMuscle[$muscle]['weighted'] = ($byMuscle[$muscle]['weighted'] ?? 0) + $row['pct_per_week'] * $row['sets'];
            $byMuscle[$muscle]['se'] = ($byMuscle[$muscle]['se'] ?? 0) + ($row['se_pct_per_week'] ?? 0) * $row['sets'];
            $byMuscle[$muscle]['sets'] = ($byMuscle[$muscle]['sets'] ?? 0) + $row['sets'];
            $byMuscle[$muscle]['lifts'] = ($byMuscle[$muscle]['lifts'] ?? 0) + 1;
        }

        $out = [];
        foreach ($byMuscle as $muscle => $m) {
            if ($m['sets'] === 0) {
                continue;
            }
            $pct = round($m['weighted'] / $m['sets'], 2);
            // Flat = inside the set-weighted mean of the lifts' own slope
            // standard errors — the same "distinguishable from zero" rule
            // each lift is judged by, aggregated with the same weights.
            $flatBand = round($m['se'] / $m['sets'], 2);
            $out[] = [
                'muscle' => $muscle,
                'pct_per_week' => $pct,
                'direction' => match (true) {
                    $pct > $flatBand => 'up',
                    $pct < -$flatBand => 'down',
                    default => 'flat',
                },
                'lifts' => $m['lifts'],
                'sets' => $m['sets'],
            ];
        }

        usort($out, fn ($a, $b) => $a['pct_per_week'] <=> $b['pct_per_week']);

        return $out;
    }
}
