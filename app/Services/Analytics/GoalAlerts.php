<?php

namespace App\Services\Analytics;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Goal-aware alerting. Compares observed body-composition trends to the
 * active goal's target rate and flags fat-gain / muscle-loss risks, plus
 * training-load signals read from the workout log itself.
 */
class GoalAlerts
{
    /** Recent-week hard sets at or past this multiple of the baseline weekly mean is a spike. */
    private const SPIKE_RATIO = 1.6;

    /**
     * Below this baseline (sets/week) a ratio is noise: going from 3 sets to
     * 5 is not a training decision worth an alert.
     */
    private const SPIKE_MIN_BASE_SETS = 8;

    /**
     * Sessions before a flat trend is called a stall. Fewer and a deload
     * plus a holiday reads as one.
     */
    private const STALL_MIN_SESSIONS = 6;

    public function __construct(private readonly User $user) {}

    /**
     * Rates formatted once, so every alert reads the same and a translator
     * never has to reproduce a sprintf format string.
     *
     * @return array<string, string>
     */
    private function rates(float $observed, float $target): array
    {
        return [
            'observed' => number_format($observed, 2),
            'target' => number_format($target, 2),
        ];
    }

    /** @return array<int, array{level:string, title:string, message:string}> */
    public function all(): array
    {
        $alerts = [];
        $goal = $this->user->activeGoal();
        $profile = $goal?->profile();
        $targetRate = $profile?->target_rate_pct_bw_per_week ?? 0.35;

        $bc = new BodyCompAnalytics($this->user);
        $rate = $bc->weightRateKgPerWeek();
        $part = $bc->partitioning();

        if ($rate && $rate['pct_bw_per_week'] !== null) {
            $observed = $rate['pct_bw_per_week'];
            $isBulk = $targetRate > 0.05;
            $isCut = $targetRate < -0.05;

            if ($isBulk) {
                if ($observed > $targetRate * 1.6) {
                    $alerts[] = $this->alert('warning', __('app.alerts.gaining_too_fast'),
                        __('app.alerts.gaining_too_fast_body', $this->rates($observed, $targetRate)));
                } elseif ($observed < $targetRate * 0.3) {
                    $alerts[] = $this->alert('info', __('app.alerts.bulk_stalling'),
                        __('app.alerts.bulk_stalling_body', $this->rates($observed, $targetRate)));
                } else {
                    $alerts[] = $this->alert('success', __('app.alerts.on_track'),
                        __('app.alerts.on_track_bulk_body', $this->rates($observed, $targetRate)));
                }
            } elseif ($isCut) {
                if ($observed > $targetRate * 0.3) {
                    $alerts[] = $this->alert('warning', __('app.alerts.cut_too_slow'),
                        __('app.alerts.cut_too_slow_body', $this->rates($observed, $targetRate)));
                } elseif ($observed < $targetRate * 1.6) {
                    $alerts[] = $this->alert('warning', __('app.alerts.cut_too_fast'),
                        __('app.alerts.cut_too_fast_body', $this->rates($observed, $targetRate)));
                } else {
                    $alerts[] = $this->alert('success', __('app.alerts.on_track'),
                        __('app.alerts.on_track_cut_body', $this->rates($observed, $targetRate)));
                }
            }
        }

        if ($part && $part['p_ratio'] !== null && $targetRate > 0.05 && $part['p_ratio'] < 0.5) {
            $caveat = ($part['source'] ?? 'scale') === 'scale'
                ? ' '.__('app.alerts.bia_caveat')
                : '';

            if ($part['reliable']) {
                $alerts[] = $this->alert('warning', __('app.alerts.poor_partitioning'),
                    __('app.alerts.poor_partitioning_body', [
                        'percent' => (int) round($part['p_ratio'] * 100),
                        'readings' => $part['fat_points'],
                    ]).$caveat);
            } else {
                $alerts[] = $this->alert('info', __('app.alerts.poor_partitioning_low'),
                    __('app.alerts.poor_partitioning_low_body', [
                        'percent' => (int) round($part['p_ratio'] * 100),
                    ]).$caveat);
            }
        }

        if ($part && $part['reliable'] && $part['delta_fat_pct'] !== null && $part['delta_fat_pct'] > 1.5) {
            $alerts[] = $this->alert('warning', __('app.alerts.fat_climbing'),
                __('app.alerts.fat_climbing_body', ['points' => number_format($part['delta_fat_pct'], 1)]));
        }

        $status = $bc->status();
        if ($status['waist_to_height'] !== null && $status['waist_to_height'] > 0.5) {
            $alerts[] = $this->alert('info', __('app.alerts.waist_high'), __('app.alerts.waist_high_body'));
        }

        foreach ($status['symmetry'] as $s) {
            if ($s['diff_pct'] > 5) {
                $part_name = __('app.muscles.'.$s['part']);
                $part_name = str_contains($part_name, 'app.muscles.') ? ucfirst($s['part']) : $part_name;

                $alerts[] = $this->alert('info', __('app.alerts.asymmetry', ['part' => $part_name]),
                    __('app.alerts.asymmetry_body', [
                        'part' => $part_name,
                        'percent' => number_format($s['diff_pct'], 1),
                    ]));
            }
        }

        foreach ($this->trainingAlerts() as $a) {
            $alerts[] = $a;
        }

        if (empty($alerts)) {
            $alerts[] = $this->alert('info', __('app.alerts.no_data'), __('app.alerts.no_data_body'));
        }

        return $alerts;
    }

    /**
     * Training-load alerts read from the workout log: a sharp week-on-week
     * volume ramp, and top lifts that have stopped moving. One eight-week
     * row fetch serves both rules.
     *
     * @return array<int, array{level:string, title:string, message:string}>
     */
    private function trainingAlerts(): array
    {
        $now = Carbon::now($this->user->resolvedTimezone());
        $filter = new FilterCriteria(
            from: $now->copy()->subWeeks(8)->startOfDay(),
            to: $now->copy()->endOfDay(),
        );
        $rows = (new SetQuery($this->user, $filter))->rows();

        if ($rows->isEmpty()) {
            return [];
        }

        $alerts = [];
        if ($spike = $this->volumeSpike($rows, $now)) {
            $alerts[] = $spike;
        }
        if ($stalled = $this->stalledLifts($rows, $filter)) {
            $alerts[] = $stalled;
        }

        return $alerts;
    }

    /**
     * Hard sets in the last seven days against the weekly mean of the four
     * weeks before them. The injury literature on load spikes is mixed, so
     * the body text says "associated with", not "causes" — but a >60% ramp
     * is worth a deliberate look either way.
     */
    private function volumeSpike(Collection $rows, Carbon $now): ?array
    {
        $boundary = $now->copy()->subDays(7);
        $windowStart = $now->copy()->subDays(35);

        $recent = 0;
        $prior = 0;
        $earliest = null;

        foreach ($rows as $r) {
            if (! $r->start_time) {
                continue;
            }
            $t = Carbon::parse($r->start_time);
            if ($earliest === null || $t->lt($earliest)) {
                $earliest = $t;
            }
            if ($t->lt($windowStart)) {
                continue;
            }
            $t->greaterThanOrEqualTo($boundary) ? $recent++ : $prior++;
        }

        // A spike needs a baseline: at least three weeks of history before
        // the recent window, else the ramp is just "started training".
        if ($earliest === null || $earliest->diffInDays($boundary) < 21) {
            return null;
        }

        $priorDays = min(28.0, $earliest->diffInDays($boundary));
        $weeklyMean = $prior / ($priorDays / 7);

        if ($weeklyMean < self::SPIKE_MIN_BASE_SETS || $recent < $weeklyMean * self::SPIKE_RATIO) {
            return null;
        }

        return $this->alert('warning', __('app.alerts.volume_spike'),
            __('app.alerts.volume_spike_body', [
                'recent' => $recent,
                'baseline' => (int) round($weeklyMean),
            ]));
    }

    /** Top-three lifts by e1RM whose eight-week trend is not going up. */
    private function stalledLifts(Collection $rows, FilterCriteria $filter): ?array
    {
        // In a cut, holding strength in a deficit is success, not a stall.
        if ($this->user->activeGoal()?->type === 'cut') {
            return null;
        }

        $sa = new StrengthAnalytics($this->user, $filter);
        $prs = array_filter($sa->exercisePrs($rows), fn ($p) => $p['best_e1rm'] > 0);
        $trends = $sa->exerciseTrends($rows);

        $stalled = [];
        $creep = null;
        foreach (array_slice($prs, 0, 3) as $p) {
            $key = $p['template_id'] ?? $p['exercise'];
            $t = $trends[$key] ?? null;

            // Reliability (r²) is deliberately not required here: a genuinely
            // flat line explains no variance, so its r² is near zero and the
            // flag would exclude exactly the lifts this alert exists to catch.
            if ($t !== null && $t['sessions'] >= self::STALL_MIN_SESSIONS && $t['direction'] !== 'up') {
                $stalled[] = $p['exercise'];
                if (($rise = $this->rpeCreep($rows, $key)) !== null) {
                    $creep = max($creep ?? 0.0, $rise);
                }
            }
        }

        if ($stalled === []) {
            return null;
        }

        $body = __('app.alerts.stalled_lifts_body', ['lifts' => implode(', ', $stalled)]);

        // Flat bar + rising effort at the same load is the classic accumulated-
        // fatigue picture, and the honest deload pitch: the one direct study
        // (Coleman 2024) found a week off cost no muscle — it buys recovery,
        // not growth.
        if ($creep !== null) {
            $body .= ' '.__('app.alerts.stalled_deload', ['delta' => number_format($creep, 1)]);
        }

        return $this->alert('info', __('app.alerts.stalled_lifts'), $body);
    }

    /**
     * Rising effort at an unchanged load: mean top-set RPE across the later
     * half of matched-load sessions minus the earlier half. Returns the rise
     * when it clears 0.8 RPE with at least four matched sessions, else null —
     * lifts without logged RPE simply cannot creep.
     */
    private function rpeCreep(Collection $rows, string $key): ?float
    {
        $byDay = [];
        foreach ($rows as $r) {
            $rowKey = $r->exercise_template_hevy_id ?? $r->exercise_title;
            if ($rowKey !== $key || ! $r->start_time || $r->rpe === null || ! $r->weight_kg) {
                continue;
            }
            $day = PeriodService::localDate(Carbon::parse($r->start_time), $this->user->resolvedTimezone());
            $current = $byDay[$day] ?? null;
            if ($current === null || (float) $r->weight_kg > (float) $current->weight_kg) {
                $byDay[$day] = $r;
            }
        }

        if (count($byDay) < 4) {
            return null;
        }

        ksort($byDay);
        $sets = array_values($byDay);
        $latestLoad = (float) end($sets)->weight_kg;

        $matched = array_values(array_filter(
            $sets,
            fn ($s) => abs((float) $s->weight_kg - $latestLoad) <= $latestLoad * 0.025
        ));

        if (count($matched) < 4) {
            return null;
        }

        $half = intdiv(count($matched), 2);
        $mean = fn (array $chunk) => array_sum(array_map(fn ($s) => (float) $s->rpe, $chunk)) / count($chunk);

        $delta = $mean(array_slice($matched, -$half)) - $mean(array_slice($matched, 0, $half));

        return $delta >= 0.8 ? round($delta, 1) : null;
    }

    private function alert(string $level, string $title, string $message): array
    {
        return compact('level', 'title', 'message');
    }
}
