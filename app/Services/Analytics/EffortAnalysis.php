<?php

namespace App\Services\Analytics;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Where the effort actually lands, from logged RPE.
 *
 * Robinson et al. (2024, 55-study meta-regression) found hypertrophy rises
 * as sets approach failure, while strength gains barely care. So a muscle
 * trained mostly at RPE ≤ 6.5 (roughly four or more reps in reserve) is
 * getting less growth stimulus per set than its volume suggests — worth
 * knowing, never worth inventing: with thin RPE coverage this stays silent
 * instead of guessing what unlogged effort felt like.
 */
class EffortAnalysis
{
    private const WINDOW_DAYS = 28;

    /** At or below this RPE a set sits ~4+ reps from failure. */
    private const FAR_RPE = 6.5;

    /** Below this share of sets carrying RPE, the sample is the loggers, not the training. */
    private const MIN_COVERAGE = 0.5;

    private const MIN_RPE_SETS = 10;

    /** RPE-carrying sets a muscle needs in the window before it can be flagged. */
    private const MIN_MUSCLE_SETS = 6;

    /** Share of far-from-failure sets at which a muscle is flagged. */
    private const FLAG_SHARE = 0.5;

    public function __construct(private readonly User $user) {}

    /**
     * @return array{
     *     total_sets: int,
     *     coverage_pct: int,
     *     enough: bool,
     *     flagged: array<int, array{muscle: string, far_pct: int, sets: int}>,
     * }
     */
    public function summary(): array
    {
        $now = Carbon::now($this->user->resolvedTimezone());
        $rows = (new SetQuery($this->user, new FilterCriteria(
            from: $now->copy()->subDays(self::WINDOW_DAYS)->startOfDay(),
            to: $now->copy()->endOfDay(),
        )))->rows();

        $total = 0;
        $withRpe = 0;
        $byMuscle = [];

        foreach ($rows as $r) {
            if (! $r->reps) {
                continue;
            }
            $total++;
            if ($r->rpe === null) {
                continue;
            }
            $withRpe++;
            if (! $r->primary_muscle_group) {
                continue;
            }
            $m = $r->primary_muscle_group;
            $byMuscle[$m]['sets'] = ($byMuscle[$m]['sets'] ?? 0) + 1;
            if ((float) $r->rpe <= self::FAR_RPE) {
                $byMuscle[$m]['far'] = ($byMuscle[$m]['far'] ?? 0) + 1;
            }
        }

        $coverage = $total > 0 ? $withRpe / $total : 0.0;
        $enough = $coverage >= self::MIN_COVERAGE && $withRpe >= self::MIN_RPE_SETS;

        $flagged = [];
        if ($enough) {
            foreach ($byMuscle as $muscle => $d) {
                if ($d['sets'] < self::MIN_MUSCLE_SETS) {
                    continue;
                }
                $share = ($d['far'] ?? 0) / $d['sets'];
                if ($share >= self::FLAG_SHARE) {
                    $flagged[] = [
                        'muscle' => $muscle,
                        'far_pct' => (int) round($share * 100),
                        'sets' => $d['sets'],
                    ];
                }
            }
            usort($flagged, fn ($a, $b) => $b['far_pct'] <=> $a['far_pct']);
        }

        return [
            'total_sets' => $total,
            'coverage_pct' => (int) round($coverage * 100),
            'enough' => $enough,
            'flagged' => $flagged,
        ];
    }
}
