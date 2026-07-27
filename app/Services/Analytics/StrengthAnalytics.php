<?php

namespace App\Services\Analytics;

use App\Models\User;
use App\Science\Stats\LinearRegression;
use App\Science\Strength\OneRepMax;
use App\Science\Strength\StrengthScore;
use App\Services\StrengthStandards\StrengthAssessor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StrengthAnalytics
{
    public function __construct(
        private readonly User $user,
        private readonly FilterCriteria $filter,
    ) {}

    private function rows(): Collection
    {
        return (new SetQuery($this->user, $this->filter))->rows();
    }

    /**
     * Best e1RM per session for the (single) filtered exercise, as a time series.
     * Requires an exercise filter to be meaningful.
     */
    public function e1rmSeries(?Collection $rows = null): array
    {
        $rows ??= $this->rows();
        $perSession = [];
        foreach ($rows as $r) {
            if (! $r->start_time) {
                continue;
            }
            $e = OneRepMax::estimate($r->weight_kg, $r->reps, $r->rpe);
            // Reliability must consider RPE as well as reps: a low-RPE set's
            // e1RM is inflated by inferred reps-in-reserve, not by a lift.
            if ($e === null || ! OneRepMax::isReliableSet((float) $r->reps, $r->rpe !== null ? (float) $r->rpe : null)) {
                continue;
            }
            $day = PeriodService::localDate(Carbon::parse($r->start_time), $this->user->resolvedTimezone());
            $perSession[$day] = max($perSession[$day] ?? 0, $e);
        }
        ksort($perSession);

        return array_map(fn ($k, $v) => ['label' => $k, 'value' => round($v, 1)], array_keys($perSession), $perSession);
    }

    /**
     * Per-exercise best e1RM, best weight, and rep PR across the filtered range.
     */
    public function exercisePrs(?Collection $rows = null): array
    {
        $rows ??= $this->rows();
        $byExercise = [];
        foreach ($rows as $r) {
            $key = $r->exercise_template_hevy_id ?? $r->exercise_title;
            if (! isset($byExercise[$key])) {
                $byExercise[$key] = [
                    'exercise' => $r->exercise_title,
                    'template_id' => $r->exercise_template_hevy_id,
                    'muscle' => $r->primary_muscle_group,
                    'best_e1rm' => 0.0,
                    'best_weight' => 0.0,
                    'best_reps' => 0,
                    'best_volume_set' => 0.0,
                ];
            }
            $e = OneRepMax::estimate($r->weight_kg, $r->reps, $r->rpe);
            if ($e !== null && OneRepMax::isReliableSet((float) $r->reps, $r->rpe !== null ? (float) $r->rpe : null)) {
                $byExercise[$key]['best_e1rm'] = max($byExercise[$key]['best_e1rm'], $e);
            }
            $byExercise[$key]['best_weight'] = max($byExercise[$key]['best_weight'], (float) ($r->weight_kg ?? 0));
            $byExercise[$key]['best_reps'] = max($byExercise[$key]['best_reps'], (int) ($r->reps ?? 0));
            $byExercise[$key]['best_volume_set'] = max($byExercise[$key]['best_volume_set'], (float) ($r->weight_kg ?? 0) * (float) ($r->reps ?? 0));
        }

        $out = array_values($byExercise);
        usort($out, fn ($a, $b) => $b['best_e1rm'] <=> $a['best_e1rm']);

        return $out;
    }

    /**
     * Sessions below this cannot support a trend. Three points can be fitted but
     * a single unusually good or bad day dominates the line; four is the least
     * that lets one outlier be outvoted.
     */
    private const MIN_SESSIONS_FOR_TREND = 4;

    /**
     * Slope, as a fraction of the lift's own mean e1RM per week, below which the
     * lift is called flat. 0.0005 is ~2.6%/year: enough that a 160kg squat has to
     * be moving more than about 4kg a year to count as progressing, which is a
     * low bar deliberately — the goal is to catch a genuine stall, not to argue
     * about whether slow progress is progress.
     *
     * Relative rather than absolute, because 2kg/year is a stall on a squat and
     * respectable on a lateral raise.
     */
    private const FLAT_SLOPE_FRACTION = 0.0005;

    /**
     * Below this R², the points are scattered enough that the direction is a
     * hint rather than a measurement, and the UI should say so.
     */
    private const RELIABLE_R2 = 0.3;

    /**
     * Per-exercise e1RM trend, so the page can answer the question it asks:
     * is the weight on the bar going up?
     *
     * exercisePrs() reports each lift's best-ever numbers, which say where an
     * athlete has been, not where they are going — a lifter who set every PR
     * eight months ago and has slid since reads identically to one still
     * climbing. This fits each lift's own e1RM history instead.
     *
     * Shares one row fetch with the caller so adding it costs no extra queries.
     *
     * @return array<string, array{direction: string, per_week: float, sessions: int, r2: float, reliable: bool}>
     *                                                                                                            Keyed by template id, falling back to the exercise title.
     */
    public function exerciseTrends(?Collection $rows = null): array
    {
        $rows ??= $this->rows();

        // Best reliable e1RM per exercise per day, mirroring e1rmSeries so the
        // trend is fitted to the same numbers the chart would draw.
        $byExercise = [];
        foreach ($rows as $r) {
            if (! $r->start_time) {
                continue;
            }

            $e = OneRepMax::estimate($r->weight_kg, $r->reps, $r->rpe);
            if ($e === null || ! OneRepMax::isReliableSet((float) $r->reps, $r->rpe !== null ? (float) $r->rpe : null)) {
                continue;
            }

            $key = $r->exercise_template_hevy_id ?? $r->exercise_title;
            $day = PeriodService::localDate(Carbon::parse($r->start_time), $this->user->resolvedTimezone());
            $byExercise[$key][$day] = max($byExercise[$key][$day] ?? 0, $e);
        }

        $out = [];
        foreach ($byExercise as $key => $perDay) {
            if (count($perDay) < self::MIN_SESSIONS_FOR_TREND) {
                continue;
            }

            ksort($perDay);
            $days = array_keys($perDay);
            $base = Carbon::parse($days[0]);

            $x = [];
            $y = [];
            foreach ($perDay as $day => $value) {
                // Days elapsed since the first session. Carbon 3's diffInDays is
                // signed, so the earlier date has to be the receiver or every
                // slope comes out negated.
                $x[] = (float) $base->diffInDays(Carbon::parse($day));
                $y[] = (float) $value;
            }

            $fit = LinearRegression::fit($x, $y);
            $mean = array_sum($y) / count($y);
            if ($mean <= 0) {
                continue;
            }

            $perWeek = $fit->slope * 7;
            $threshold = $mean * self::FLAT_SLOPE_FRACTION * 7;

            $out[$key] = [
                'direction' => match (true) {
                    $perWeek > $threshold => 'up',
                    $perWeek < -$threshold => 'down',
                    default => 'flat',
                },
                'per_week' => round($perWeek, 2),
                'sessions' => count($perDay),
                'r2' => round($fit->r2, 3),
                'reliable' => $fit->r2 >= self::RELIABLE_R2,
            ];
        }

        return $out;
    }

    /** Current best e1RM (single number) for the filtered set. */
    public function currentBestE1rm(?Collection $rows = null): ?float
    {
        $series = $this->e1rmSeries($rows);

        return $series ? end($series)['value'] : null;
    }

    /** Bodyweight-adjusted score for a lift using latest bodyweight. */
    public function strengthScores(float $liftedKg): array
    {
        $bw = $this->user->bodyMeasurements()
            ->whereNotNull('weight_kg')->orderByDesc('date')->value('weight_kg');

        if (! $bw) {
            return ['wilks' => null, 'dots' => null, 'relative' => null];
        }

        $sex = $this->user->sex ?? 'male';

        return [
            'wilks' => StrengthScore::wilks($liftedKg, (float) $bw, $sex),
            'dots' => StrengthScore::dots($liftedKg, (float) $bw, $sex),
            'relative' => StrengthScore::relative($liftedKg, (float) $bw),
        ];
    }

    /**
     * Strength-level placement (Beginner→Elite + percentile) for every mapped
     * exercise in the filtered range, using each exercise's best e1RM and the
     * lifter's latest bodyweight, sex and age. Uses the layered assessor
     * (FitnessVolt → OpenPowerlifting → built-in model).
     *
     * @return array<int, array>
     */
    public function strengthLevels(?Collection $rows = null): array
    {
        $bw = $this->user->bodyMeasurements()
            ->whereNotNull('weight_kg')->orderByDesc('date')->value('weight_kg');
        if (! $bw) {
            return [];
        }
        $sex = $this->user->sex ?? 'male';
        $age = $this->user->age;

        $assessor = app(StrengthAssessor::class);

        $out = [];
        foreach ($this->exercisePrs($rows) as $pr) {
            if (! $pr['best_e1rm']) {
                continue;
            }
            $eval = $assessor->assess($pr['exercise'] ?? '', (float) $pr['best_e1rm'], (float) $bw, $sex, $age);
            if (! $eval) {
                continue;
            }

            $out[] = array_merge($eval, [
                'exercise' => $pr['exercise'],
                'template_id' => $pr['template_id'],
                'muscle' => $pr['muscle'],
            ]);
        }

        usort($out, fn ($a, $b) => $b['percentile'] <=> $a['percentile']);

        return $out;
    }

    /** Strength level for the single exercise currently in the filter, if any. */
    public function strengthLevelForCurrent(?Collection $rows = null): ?array
    {
        $levels = $this->strengthLevels($rows);

        if (! $this->filter->exerciseTemplateHevyId) {
            return $levels[0] ?? null;
        }

        foreach ($levels as $l) {
            if ($l['template_id'] === $this->filter->exerciseTemplateHevyId) {
                return $l;
            }
        }

        return null;
    }
}
