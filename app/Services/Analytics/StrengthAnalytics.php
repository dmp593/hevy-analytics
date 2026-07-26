<?php

namespace App\Services\Analytics;

use App\Models\User;
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
