<?php

namespace App\Services\Analytics;

use App\Models\User;
use App\Science\Volume\MuscleLandmarks;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VolumeAnalytics
{
    public function __construct(
        private readonly User $user,
        private readonly FilterCriteria $filter,
    ) {}

    private function rows(): Collection
    {
        return (new SetQuery($this->user, $this->filter))->rows();
    }

    /** Total tonnage = sum(weight * reps) over working sets. */
    public function tonnage(?Collection $rows = null): float
    {
        $rows ??= $this->rows();

        return round($rows->sum(fn ($r) => (float) ($r->weight_kg ?? 0) * (float) ($r->reps ?? 0)), 1);
    }

    public function totalSets(?Collection $rows = null): int
    {
        return ($rows ?? $this->rows())->count();
    }

    public function totalReps(?Collection $rows = null): int
    {
        return (int) ($rows ?? $this->rows())->sum(fn ($r) => (float) ($r->reps ?? 0));
    }

    /** Tonnage time series bucketed by the filter period. */
    public function tonnageSeries(?Collection $rows = null): array
    {
        $rows ??= $this->rows();
        $buckets = [];
        foreach ($rows as $r) {
            if (! $r->start_time) {
                continue;
            }
            $key = PeriodService::bucketKey(Carbon::parse($r->start_time), $this->filter->period, $this->user->resolvedTimezone());
            $buckets[$key] = ($buckets[$key] ?? 0) + (float) ($r->weight_kg ?? 0) * (float) ($r->reps ?? 0);
        }
        ksort($buckets);

        return array_map(fn ($k, $v) => ['label' => $k, 'value' => round($v, 1)], array_keys($buckets), $buckets);
    }

    /**
     * Weekly hard-set count per muscle group (primary = 1 set, secondary =
     * fractional), classified against RP landmarks. Uses distinct calendar
     * weeks in range to compute the per-week average.
     */
    public function weeklySetsPerMuscle(?Collection $rows = null): array
    {
        $rows ??= $this->rows();
        $weeks = max(1, $this->weeksInRange($rows));

        $totals = [];
        foreach ($rows as $r) {
            if ($r->primary_muscle_group) {
                $totals[$r->primary_muscle_group] = ($totals[$r->primary_muscle_group] ?? 0) + 1;
            }
            foreach ($r->secondary_muscle_groups as $sec) {
                $totals[$sec] = ($totals[$sec] ?? 0) + $this->filter->secondaryMuscleWeight;
            }
        }

        $out = [];
        foreach ($totals as $muscle => $sets) {
            $perWeek = round($sets / $weeks, 1);
            $out[] = [
                'muscle' => $muscle,
                'total_sets' => round($sets, 1),
                'per_week' => $perWeek,
                'status' => MuscleLandmarks::classify($muscle, $perWeek),
                'landmarks' => MuscleLandmarks::for($muscle),
            ];
        }

        usort($out, fn ($a, $b) => $b['per_week'] <=> $a['per_week']);

        return $out;
    }

    /** Tonnage distribution per muscle (for radar/pie). */
    public function volumePerMuscle(?Collection $rows = null): array
    {
        $rows ??= $this->rows();
        $totals = [];
        foreach ($rows as $r) {
            $vol = (float) ($r->weight_kg ?? 0) * (float) ($r->reps ?? 0);
            if ($r->primary_muscle_group) {
                $totals[$r->primary_muscle_group] = ($totals[$r->primary_muscle_group] ?? 0) + $vol;
            }
            foreach ($r->secondary_muscle_groups as $sec) {
                $totals[$sec] = ($totals[$sec] ?? 0) + $vol * $this->filter->secondaryMuscleWeight;
            }
        }
        arsort($totals);

        return array_map(fn ($k, $v) => ['muscle' => $k, 'tonnage' => round($v, 1)], array_keys($totals), $totals);
    }

    /**
     * Number of whole weeks to divide by for "per week" figures.
     *
     * Neither the raw filter window nor the raw data span is right on its own:
     *
     *  - Dividing by the filter window punishes a new account. Someone three
     *    weeks into training, viewed through the default six-month window, would
     *    have their volume divided by 26.
     *  - Dividing by the data span overstates anyone who trained in a short
     *    burst, and mis-counts dense data: measuring first-session to
     *    last-session under-counts by a week, which the old code papered over
     *    with a blanket +1 that then over-counted every other case by 20%.
     *
     * So: start at the later of the window opening and the athlete's first
     * session — they cannot train before they began — and end at the window
     * close, capped at today. Counting past the last session is deliberate:
     * someone who has stopped training should watch their weekly average fall.
     */
    public function weeksInRange(?Collection $rows = null): int
    {
        $dates = ($rows ?? $this->rows())->pluck('start_time')->filter();
        if ($dates->isEmpty()) {
            return 1;
        }

        $firstSession = Carbon::parse($dates->min());

        $end = $this->filter->to?->copy() ?? Carbon::parse($dates->max());
        if ($end->greaterThan(Carbon::now())) {
            $end = Carbon::now();
        }

        $windowStart = $this->filter->from?->copy();

        // Compare against the athlete's FIRST EVER session, not the first in
        // range: rows are already filtered to the window, so the earliest row is
        // never before the window opens and the comparison would always fail.
        $firstEver = $this->user->workouts()->min('start_time');
        $trainedBeforeWindow = $windowStart !== null
            && $firstEver !== null
            && Carbon::parse($firstEver)->lessThanOrEqualTo($windowStart);

        // Which edge binds changes how the weeks are counted, and getting this
        // wrong is what produced both the original 20% understatement and its
        // over-correction.
        if ($trainedBeforeWindow) {
            // The window opens after training began, so the window IS the period.
            // 28 requested days is 4 weeks, no fencepost adjustment.
            $days = max(0, $windowStart->diffInDays($end));

            return max(1, (int) round($days / 7));
        }

        // Training began inside the window, so the athlete's history is the
        // period. Measuring first-session to last spans one week fewer than it
        // covers -- four weekly sessions span 21 days but occupy 4 weeks -- so
        // count the days inclusively.
        $days = max(0, $firstSession->diffInDays($end));

        return max(1, (int) ceil(($days + 1) / 7));
    }
}
