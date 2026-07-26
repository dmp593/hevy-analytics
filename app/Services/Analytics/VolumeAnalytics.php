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
            $key = PeriodService::bucketKey(Carbon::parse($r->start_time), $this->filter->period);
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
     * Number of whole weeks the analysis window covers, used as the divisor for
     * "per week" figures.
     *
     * Prefer the requested filter window over the observed data span: a user who
     * trained on days 1 and 3 of a 28-day window has still only trained for one
     * week's worth of sessions across four weeks, and dividing by the observed
     * 3-day span would overstate their weekly volume ~9x. Falls back to the data
     * span when the filter is open-ended.
     */
    public function weeksInRange(?Collection $rows = null): int
    {
        [$from, $to] = $this->windowBounds($rows);

        if ($from === null || $to === null) {
            return 1;
        }

        return max(1, (int) round($from->diffInDays($to) / 7));
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function windowBounds(?Collection $rows): array
    {
        if ($this->filter->from && $this->filter->to) {
            return [$this->filter->from->copy(), $this->filter->to->copy()];
        }

        $dates = ($rows ?? $this->rows())->pluck('start_time')->filter();
        if ($dates->isEmpty()) {
            return [null, null];
        }

        return [
            $this->filter->from?->copy() ?? Carbon::parse($dates->min()),
            $this->filter->to?->copy() ?? Carbon::parse($dates->max()),
        ];
    }
}
