<?php

namespace App\Services\Analytics;

use App\Models\Routine;
use App\Models\User;
use App\Science\Stats\LinearRegression;
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

        return $this->summariseSessions($rows);
    }

    /**
     * @param  iterable<int, object>  $rows
     */
    private function summariseSessions(iterable $rows): array
    {
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

    /**
     * Summary stats for every routine (adherence, volume, progression).
     *
     * Reads the full set of rows ONCE and groups by routine in PHP. Calling
     * sessionSeries() per routine re-ran the whole joined set query for each
     * one — a textbook N+1 that scaled with how many routines a user had built.
     */
    public function overview(): array
    {
        $rows = (new SetQuery($this->user, $this->filter))->rows();

        $byRoutine = [];
        foreach ($rows as $row) {
            if ($row->routine_hevy_id === null) {
                continue;
            }
            $byRoutine[$row->routine_hevy_id][] = $row;
        }

        $routines = $this->user->routines()->with('folder')->get();
        $out = [];
        foreach ($routines as $routine) {
            $series = $this->summariseSessions($byRoutine[$routine->hevy_id] ?? []);
            if (empty($series)) {
                continue;
            }
            $tonnages = array_column($series, 'tonnage');
            $trend = $this->progressionTrend($tonnages);

            $out[] = [
                'routine' => $routine->title,
                // Hevy titles are not unique — the folder is what tells two
                // "Treino A" rows apart in the overview table.
                'folder' => $routine->folder?->title,
                'routine_id' => $routine->hevy_id,
                'sessions' => count($series),
                'avg_tonnage' => round(array_sum($tonnages) / count($tonnages), 0),
                'first_tonnage' => $tonnages[0],
                'last_tonnage' => end($tonnages),
                'progression_pct' => $trend['pct'],
                'progression_reliable' => $trend['reliable'],
                'last_performed' => end($series)['date'],
            ];
        }
        usort($out, fn ($a, $b) => strcmp($b['last_performed'], $a['last_performed']));

        return $out;
    }

    /**
     * Progression as a fitted TREND across every session, not first versus last.
     *
     * Comparing two single sessions makes the answer depend entirely on which
     * two they are. On a normal programme the most recent session is often a
     * deload, which reported every routine in the demo account as roughly -31%
     * "progression" while loads were in fact climbing the whole time. A slope
     * over all sessions is not fooled by where the window happens to land.
     *
     * @param  array<int, float>  $tonnages  in session order
     * @return array{pct: ?float, reliable: bool}
     */
    private function progressionTrend(array $tonnages): array
    {
        $n = count($tonnages);
        $mean = $n > 0 ? array_sum($tonnages) / $n : 0.0;

        // Under four sessions a line through the points says nothing, and a zero
        // mean makes the percentage meaningless.
        if ($n < 4 || $mean <= 0) {
            return ['pct' => null, 'reliable' => false];
        }

        $regression = LinearRegression::fit(range(0, $n - 1), $tonnages);

        // Slope is tonnage per session; express the whole span as a percentage
        // of the average session so routines of different sizes compare.
        $change = $regression->slope * ($n - 1);

        return [
            'pct' => round($change / $mean * 100, 1),
            'reliable' => $regression->r2 >= 0.3,
        ];
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
