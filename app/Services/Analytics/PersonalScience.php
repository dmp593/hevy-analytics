<?php

namespace App\Services\Analytics;

use App\Models\User;
use App\Science\Strength\OneRepMax;
use Illuminate\Support\Carbon;

/**
 * Your own data, run as the study.
 *
 * Three questions the literature answers only on population averages,
 * answered here on the athlete's log instead: how many rest days YOUR
 * lifts respond best to, whether YOU are stronger in the morning or the
 * evening (Grgic 2019 says evening on average), and where YOUR sets live
 * across the load spectrum (Schoenfeld 2021: hypertrophy is similar across
 * rep ranges at matched effort; strength still needs heavy work).
 *
 * Everything is expressed relative to each lift's own mean e1RM in the
 * window, so a squat and a curl aggregate on the same scale, and every
 * number ships with its sample size — small n is said, never hidden.
 */
class PersonalScience
{
    private const WINDOW_MONTHS = 6;

    /** Sessions a lift needs before its gaps can say anything. */
    private const MIN_SESSIONS = 8;

    /** Rest gaps longer than this are layoffs, not recovery choices. */
    private const MAX_GAP_DAYS = 7;

    private const MIN_BUCKET_N = 3;

    /** Sessions per time-of-day bucket before a lift contributes. */
    private const MIN_TIME_N = 4;

    private const PORTFOLIO_WEEKS = 12;

    private const MIN_PORTFOLIO_SETS = 10;

    public function __construct(private readonly User $user) {}

    /**
     * Per-lift session history: local day => [e1rm, hour], best reliable
     * set per day, for lifts inside the window. One fetch serves all three
     * reads.
     *
     * @return array<string, array{title: string, sessions: array<string, array{e1rm: float, hour: int}>}>
     */
    private function liftSessions(): array
    {
        $tz = $this->user->resolvedTimezone();
        $now = Carbon::now($tz);

        $rows = (new SetQuery($this->user, new FilterCriteria(
            from: $now->copy()->subMonths(self::WINDOW_MONTHS),
            to: $now->copy()->endOfDay(),
        )))->rows();

        $lifts = [];
        foreach ($rows as $r) {
            if (! $r->start_time) {
                continue;
            }
            $e = OneRepMax::estimate($r->weight_kg, $r->reps, $r->rpe);
            if ($e === null || ! OneRepMax::isReliableSet((float) $r->reps, $r->rpe !== null ? (float) $r->rpe : null)) {
                continue;
            }

            $key = $r->exercise_template_hevy_id ?? $r->exercise_title;
            $start = Carbon::parse($r->start_time)->setTimezone($tz);
            $day = $start->toDateString();

            $lifts[$key]['title'] = $r->exercise_title;
            if (($lifts[$key]['sessions'][$day]['e1rm'] ?? 0) < $e) {
                $lifts[$key]['sessions'][$day] = ['e1rm' => $e, 'hour' => $start->hour];
            }
        }

        foreach ($lifts as $key => $lift) {
            ksort($lifts[$key]['sessions']);
        }

        return $lifts;
    }

    /** Relative strength: a session's e1RM as ± percentage vs the lift's own mean. */
    private function relative(array $sessions): array
    {
        $values = array_column($sessions, 'e1rm');
        $mean = array_sum($values) / count($values);

        return array_map(fn ($s) => [
            'rel' => ($s['e1rm'] / $mean - 1) * 100,
            'hour' => $s['hour'],
        ], $sessions);
    }

    /**
     * How the athlete's top lifts respond to rest days. Buckets 1/2/3/4+
     * days between consecutive sessions of the SAME lift; gaps past a week
     * are layoffs and excluded.
     *
     * @return array{buckets: array<int, array{days: string, mean_rel: float, n: int}>, lifts: array<int, string>}|null
     */
    public function recoveryCurve(): ?array
    {
        $eligible = array_filter(
            $this->liftSessions(),
            fn ($l) => count($l['sessions']) >= self::MIN_SESSIONS
        );

        // Top three by best e1RM, so the answer is about the lifts that matter.
        uasort($eligible, fn ($a, $b) => max(array_column($b['sessions'], 'e1rm')) <=> max(array_column($a['sessions'], 'e1rm')));
        $top = array_slice($eligible, 0, 3, true);

        if ($top === []) {
            return null;
        }

        $buckets = [];
        foreach ($top as $lift) {
            $rel = $this->relative($lift['sessions']);
            $days = array_keys($rel);

            foreach ($days as $i => $day) {
                if ($i === 0) {
                    continue;
                }
                $gap = (int) Carbon::parse($days[$i - 1])->diffInDays(Carbon::parse($day));
                if ($gap < 1 || $gap > self::MAX_GAP_DAYS) {
                    continue;
                }
                $bucket = min($gap, 4);
                $buckets[$bucket][] = $rel[$day]['rel'];
            }
        }

        $out = [];
        foreach ([1, 2, 3, 4] as $bucket) {
            $samples = $buckets[$bucket] ?? [];
            if (count($samples) < self::MIN_BUCKET_N) {
                continue;
            }
            $out[] = [
                'days' => $bucket === 4 ? '4+' : (string) $bucket,
                'mean_rel' => round(array_sum($samples) / count($samples), 1),
                'n' => count($samples),
            ];
        }

        if (count($out) < 2) {
            return null; // one bucket is a fact, not a curve
        }

        return [
            'buckets' => $out,
            'lifts' => array_values(array_map(fn ($l) => $l['title'], $top)),
        ];
    }

    /**
     * Morning / afternoon / evening relative strength, aggregated across
     * lifts that were trained in at least two of the buckets.
     *
     * @return array{buckets: array<string, array{mean_rel: float, n: int}>, lifts: int}|null
     */
    public function timeOfDay(): ?array
    {
        $slot = fn (int $hour) => match (true) {
            $hour < 12 => 'morning',
            $hour < 17 => 'afternoon',
            default => 'evening',
        };

        $agg = [];
        $liftCount = 0;

        foreach ($this->liftSessions() as $lift) {
            if (count($lift['sessions']) < self::MIN_SESSIONS) {
                continue;
            }

            $bySlot = [];
            foreach ($this->relative($lift['sessions']) as $s) {
                $bySlot[$slot($s['hour'])][] = $s['rel'];
            }

            $qualified = array_filter($bySlot, fn ($v) => count($v) >= self::MIN_TIME_N);
            if (count($qualified) < 2) {
                continue;
            }

            $liftCount++;
            foreach ($qualified as $name => $values) {
                $agg[$name] = array_merge($agg[$name] ?? [], $values);
            }
        }

        if ($liftCount === 0 || count($agg) < 2) {
            return null;
        }

        $buckets = [];
        foreach (['morning', 'afternoon', 'evening'] as $name) {
            if (isset($agg[$name])) {
                $buckets[$name] = [
                    'mean_rel' => round(array_sum($agg[$name]) / count($agg[$name]), 1),
                    'n' => count($agg[$name]),
                ];
            }
        }

        return ['buckets' => $buckets, 'lifts' => $liftCount];
    }

    /**
     * Where the working sets live across the load spectrum, per muscle:
     * shares of 1-5 / 6-12 / 13-20 / 21+ reps over the last twelve weeks.
     *
     * @return array{muscles: array<int, array{muscle: string, bands: array<string, int>, sets: int}>, strength_gap: bool}|null
     */
    public function repRangePortfolio(): ?array
    {
        $tz = $this->user->resolvedTimezone();
        $now = Carbon::now($tz);

        $rows = (new SetQuery($this->user, new FilterCriteria(
            from: $now->copy()->subWeeks(self::PORTFOLIO_WEEKS)->startOfDay(),
            to: $now->copy()->endOfDay(),
        )))->rows();

        $band = fn (int $reps) => match (true) {
            $reps <= 5 => 'b1_5',
            $reps <= 12 => 'b6_12',
            $reps <= 20 => 'b13_20',
            default => 'b21',
        };

        $byMuscle = [];
        $heavyShareTotal = ['heavy' => 0, 'all' => 0];

        foreach ($rows as $r) {
            if (! $r->reps || ! $r->primary_muscle_group) {
                continue;
            }
            $m = $r->primary_muscle_group;
            $b = $band((int) $r->reps);
            $byMuscle[$m]['bands'][$b] = ($byMuscle[$m]['bands'][$b] ?? 0) + 1;
            $byMuscle[$m]['sets'] = ($byMuscle[$m]['sets'] ?? 0) + 1;
            $heavyShareTotal['all']++;
            if ($b === 'b1_5') {
                $heavyShareTotal['heavy']++;
            }
        }

        $muscles = [];
        foreach ($byMuscle as $muscle => $d) {
            if ($d['sets'] < self::MIN_PORTFOLIO_SETS) {
                continue;
            }
            $bands = [];
            foreach (['b1_5', 'b6_12', 'b13_20', 'b21'] as $b) {
                $bands[$b] = (int) round(($d['bands'][$b] ?? 0) / $d['sets'] * 100);
            }
            $muscles[] = ['muscle' => $muscle, 'bands' => $bands, 'sets' => $d['sets']];
        }

        if ($muscles === []) {
            return null;
        }

        usort($muscles, fn ($a, $b) => $b['sets'] <=> $a['sets']);

        // The one goal-aware judgement: a strength goal trained with almost
        // no 1-5 work is leaving specificity on the table.
        $strengthGap = $this->user->activeGoal()?->type === 'strength'
            && $heavyShareTotal['all'] > 0
            && $heavyShareTotal['heavy'] / $heavyShareTotal['all'] < 0.05;

        return ['muscles' => $muscles, 'strength_gap' => $strengthGap];
    }
}
