<?php

namespace App\Services\Analytics;

use App\Models\Routine;
use App\Models\User;
use App\Science\Volume\MuscleLandmarks;
use Illuminate\Support\Carbon;

/**
 * Routine-level and routine->exercise-level performance analysis.
 */
class RoutineAnalytics
{
    public function __construct(
        private readonly User $user,
        private readonly FilterCriteria $filter,
    ) {}

    /** Per-session summary for one routine over time. */
    public function sessionSeries(string $routineHevyId): array
    {
        $filter = clone $this->filter;
        $filter->routineHevyId = $routineHevyId;
        $rows = (new SetQuery($this->user, $filter))->rows();

        $sessions = [];
        foreach ($rows as $r) {
            if (! $r->start_time) {
                continue;
            }
            $key = PeriodService::localDate(Carbon::parse($r->start_time), $this->user->resolvedTimezone());
            $sessions[$key] ??= ['date' => $key, 'tonnage' => 0.0, 'sets' => 0, 'reps' => 0, 'rpe_sum' => 0.0, 'rpe_n' => 0];
            $sessions[$key]['tonnage'] += (float) ($r->weight_kg ?? 0) * (float) ($r->reps ?? 0);
            $sessions[$key]['sets']++;
            $sessions[$key]['reps'] += (int) ($r->reps ?? 0);
            if ($r->rpe !== null) {
                $sessions[$key]['rpe_sum'] += (float) $r->rpe;
                $sessions[$key]['rpe_n']++;
            }
        }
        ksort($sessions);

        return array_map(function ($s) {
            return [
                'date' => $s['date'],
                'tonnage' => round($s['tonnage'], 1),
                'sets' => $s['sets'],
                'reps' => $s['reps'],
                'avg_rpe' => $s['rpe_n'] ? round($s['rpe_sum'] / $s['rpe_n'], 1) : null,
            ];
        }, array_values($sessions));
    }

    /** Summary stats for every routine (adherence, volume, progression). */
    public function overview(): array
    {
        $routines = $this->user->routines()->get();
        $out = [];
        foreach ($routines as $routine) {
            $series = $this->sessionSeries($routine->hevy_id);
            if (empty($series)) {
                continue;
            }
            $tonnages = array_column($series, 'tonnage');
            $out[] = [
                'routine' => $routine->title,
                'routine_id' => $routine->hevy_id,
                'sessions' => count($series),
                'avg_tonnage' => round(array_sum($tonnages) / count($tonnages), 0),
                'first_tonnage' => $tonnages[0],
                'last_tonnage' => end($tonnages),
                'progression_pct' => $tonnages[0] > 0
                    ? round((end($tonnages) - $tonnages[0]) / $tonnages[0] * 100, 1)
                    : null,
                'last_performed' => end($series)['date'],
            ];
        }
        usort($out, fn ($a, $b) => strcmp($b['last_performed'], $a['last_performed']));

        return $out;
    }

    /** Muscle coverage of a routine's prescribed sets vs weekly landmarks. */
    public function muscleCoverage(Routine $routine): array
    {
        $templates = $this->user->exerciseTemplates()->get()->keyBy('hevy_id');
        $totals = [];
        foreach ($routine->exercises as $ex) {
            $tpl = $templates->get($ex->exercise_template_hevy_id);
            $setCount = collect($ex->sets ?? [])->reject(fn ($s) => ($s['type'] ?? 'normal') === 'warmup')->count();
            if ($setCount === 0) {
                $setCount = count($ex->sets ?? []);
            }
            if ($tpl?->primary_muscle_group) {
                $totals[$tpl->primary_muscle_group] = ($totals[$tpl->primary_muscle_group] ?? 0) + $setCount;
            }
            foreach ($tpl?->secondary_muscle_groups ?? [] as $sec) {
                $totals[$sec] = ($totals[$sec] ?? 0) + $setCount * $this->filter->secondaryMuscleWeight;
            }
        }

        $out = [];
        foreach ($totals as $muscle => $sets) {
            $out[] = [
                'muscle' => $muscle,
                'sets_per_session' => round($sets, 1),
                'landmarks' => MuscleLandmarks::for($muscle),
            ];
        }
        usort($out, fn ($a, $b) => $b['sets_per_session'] <=> $a['sets_per_session']);

        return $out;
    }

    /** Exercises available inside a routine (for routine->exercise drilldown). */
    public function exercisesForRoutine(string $routineHevyId): array
    {
        $routine = $this->user->routines()->where('hevy_id', $routineHevyId)->with('exercises')->first();
        if (! $routine) {
            return [];
        }

        return $routine->exercises->map(fn ($e) => [
            'template_id' => $e->exercise_template_hevy_id,
            'title' => $e->title,
        ])->filter(fn ($e) => $e['template_id'])->unique('template_id')->values()->all();
    }
}
