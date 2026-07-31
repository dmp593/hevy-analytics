<?php

namespace App\Services\Hevy;

use App\Enums\SetType;
use App\Models\Routine;
use App\Models\User;
use App\Science\Strength\OneRepMax;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\PeriodService;
use App\Services\Analytics\SetQuery;
use App\Services\Analytics\StrengthAnalytics;
use Illuminate\Support\Carbon;

/**
 * Builds an evidence-based "next session" progression for a routine using
 * double progression, informed by each exercise's recent best e1RM and by
 * what the athlete actually did in their last logged session.
 *
 * Rules per working set:
 *  - Fixed weight×reps: add a rep until 12, then +2.5 kg and reset to 8 reps —
 *    but only when the last logged session earned it. A prescription the
 *    athlete missed, or only met at RPE 9.5+, is repeated rather than raised:
 *    progressing a number the body did not deliver just widens the gap
 *    between the routine and reality.
 *  - No logged session for the exercise → progress on faith (the old
 *    behaviour), because refusing to progress an unlogged exercise would
 *    punish people who only just connected their account.
 *  - Rep-range with NO planned load: prescribe from recent e1RM for the
 *    BOTTOM of the range with ~2 reps in reserve — double progression starts
 *    at the floor of the range, not past predicted failure at its ceiling.
 *    A set that already has a planned load is never overwritten.
 *  - Warm-ups are left untouched.
 *
 * Pure of side effects: returns a Hevy routine.update payload + a human-readable
 * list of changes. Persisting/pushing is the caller's job (see HevyWriter).
 */
class RoutineProgression
{
    private const LOAD_STEP_KG = 2.5;

    private const REP_CAP = 12;

    private const REP_RESET = 8;

    /** How far back a logged session still counts as "recent performance". */
    private const PERFORMANCE_WINDOW_DAYS = 45;

    /** Meeting the prescription at or above this RPE earns a repeat, not a raise. */
    private const MAXED_RPE = 9.5;

    /** Sets within 1% of the prescribed load count as "at the prescribed load". */
    private const WEIGHT_TOLERANCE = 0.99;

    /**
     * Back-off applied when a lift is both stalled and being ground out at
     * RPE 9.5+: the load comes down ~7.5% (rounded to 2.5 kg) to rebuild with
     * a rep or two in reserve. Refalo 2024 found 1-2 RIR grows muscle the
     * same as failure — the grinding buys fatigue, not size.
     */
    private const BACKOFF_FACTOR = 0.925;

    public function __construct(private readonly User $user) {}

    /**
     * @return array{payload: array<string,mixed>, changes: array<int,string>}
     */
    public function build(Routine $routine): array
    {
        $changes = [];
        $exercises = [];

        $routine->load('exercises');
        $performance = $this->recentPerformance($routine);
        $trends = $this->trends();

        foreach ($routine->exercises as $exercise) {
            $e1rm = $this->recentBestE1rm($exercise->exercise_template_hevy_id);
            $actual = $performance[$exercise->exercise_template_hevy_id] ?? null;

            $stalled = StrengthAnalytics::isStalled(
                $trends[$exercise->exercise_template_hevy_id ?? $exercise->title] ?? null
            );

            $sets = [];
            foreach ($exercise->sets ?? [] as $set) {
                $sets[] = $this->progressSet($set, $exercise->title, $e1rm, $actual, $stalled, $changes);
            }

            $exercises[] = [
                'exercise_template_id' => $exercise->exercise_template_hevy_id,
                'superset_id' => $exercise->superset_id,
                'rest_seconds' => $exercise->rest_seconds,
                'notes' => $exercise->notes,
                'sets' => $sets,
            ];
        }

        return [
            'payload' => [
                '_target_id' => $routine->hevy_id,
                'routine' => [
                    'title' => $routine->title,
                    'notes' => $routine->notes,
                    'exercises' => $exercises,
                ],
            ],
            'changes' => $changes,
        ];
    }

    /**
     * Working sets from the most recent logged session inside the window,
     * per exercise template in this routine. One query serves the whole
     * routine; an exercise never logged returns nothing.
     *
     * @return array<string, array<int, object>>
     */
    private function recentPerformance(Routine $routine): array
    {
        $templateIds = array_values(array_filter(
            $routine->exercises->pluck('exercise_template_hevy_id')->all()
        ));

        if ($templateIds === []) {
            return [];
        }

        $rows = (new SetQuery($this->user, new FilterCriteria(
            from: Carbon::now()->subDays(self::PERFORMANCE_WINDOW_DAYS),
            to: Carbon::now(),
        )))->rows();

        $byTemplate = [];
        foreach ($rows as $r) {
            if (! $r->start_time || ! in_array($r->exercise_template_hevy_id, $templateIds, true)) {
                continue;
            }
            $day = PeriodService::localDate(Carbon::parse($r->start_time), $this->user->resolvedTimezone());
            $byTemplate[$r->exercise_template_hevy_id][$day][] = $r;
        }

        $latest = [];
        foreach ($byTemplate as $template => $days) {
            krsort($days);
            $latest[$template] = reset($days);
        }

        return $latest;
    }

    /**
     * Did the last session earn a raise on this prescription?
     *
     *  - 'unknown': nothing logged recently — progress on faith.
     *  - 'missed': the prescribed load was never reached, or reached for
     *    fewer reps than prescribed.
     *  - 'maxed': prescription met, but at RPE 9.5+ — consolidate first.
     *  - 'met': prescription genuinely beaten or comfortably met.
     *
     * @param  array<int, object>|null  $actual
     */
    private function performanceVerdict(?array $actual, float $weight, int $reps): string
    {
        if (! $actual) {
            return 'unknown';
        }

        $atLoad = array_filter(
            $actual,
            fn ($s) => $s->weight_kg !== null && (float) $s->weight_kg >= $weight * self::WEIGHT_TOLERANCE
        );

        if ($atLoad === []) {
            return 'missed';
        }

        $best = null;
        foreach ($atLoad as $s) {
            if ($best === null || (int) $s->reps > (int) $best->reps) {
                $best = $s;
            }
        }

        if ((int) $best->reps < $reps) {
            return 'missed';
        }

        if ($best->rpe !== null && (float) $best->rpe >= self::MAXED_RPE) {
            return 'maxed';
        }

        return 'met';
    }

    /** Eight-week e1RM trends for every exercise, keyed like the alerts are. */
    private function trends(): array
    {
        $filter = new FilterCriteria(
            from: Carbon::now()->subWeeks(8),
            to: Carbon::now(),
        );

        return (new StrengthAnalytics($this->user, $filter))->exerciseTrends();
    }

    private function recentBestE1rm(?string $templateHevyId): ?float
    {
        if (! $templateHevyId) {
            return null;
        }

        $filter = new FilterCriteria(
            from: Carbon::now()->subMonths(3),
            to: Carbon::now(),
            exerciseTemplateHevyId: $templateHevyId,
        );

        return (new StrengthAnalytics($this->user, $filter))->currentBestE1rm();
    }

    /**
     * @param  array<int, object>|null  $actual  last logged session's sets for this exercise
     * @param  array<int, string>  $changes  (mutated by reference)
     */
    private function progressSet(array $set, ?string $title, ?float $e1rm, ?array $actual, bool $stalled, array &$changes): array
    {
        $type = SetType::fromRaw($set['type'] ?? null);

        if (! $type->isWorking()) {
            return $this->normalizeSet($set);
        }

        $weight = $set['weight_kg'] ?? null;
        $reps = $set['reps'] ?? null;
        $range = $set['rep_range'] ?? null;

        // Fixed weight × reps → double progression, gated on what the last
        // logged session actually delivered.
        if ($weight && $reps) {
            $verdict = $this->performanceVerdict($actual, (float) $weight, (int) $reps);

            if ($verdict === 'missed') {
                $changes[] = __('app.progression.keep_missed', [
                    'exercise' => $title, 'current' => "{$weight}kg×{$reps}",
                ]);

                return $this->normalizeSet($set);
            }

            if ($verdict === 'maxed') {
                // Grinding at RPE 9.5+ once earns a repeat. Grinding while the
                // eight-week trend is flat earns a back-off: the load is likely
                // past what technique and recovery can progress from. Below
                // 10 kg a 2.5 kg step is a quarter of the bar — hold instead.
                if ($stalled && $weight >= 10) {
                    $newWeight = $this->backoff((float) $weight);
                    $changes[] = __('app.progression.back_off', [
                        'exercise' => $title,
                        'from' => "{$weight}kg×{$reps}",
                        'to' => "{$newWeight}kg×{$reps}",
                    ]);
                    $set['weight_kg'] = $newWeight;

                    return $this->normalizeSet($set);
                }

                $changes[] = __('app.progression.keep_maxed', [
                    'exercise' => $title, 'current' => "{$weight}kg×{$reps}",
                ]);

                return $this->normalizeSet($set);
            }

            if ($reps >= self::REP_CAP) {
                $newWeight = $this->roundToPlate($weight + self::LOAD_STEP_KG);
                $changes[] = __('app.progression.change', [
                    'exercise' => $title,
                    'from' => "{$weight}kg×{$reps}",
                    'to' => "{$newWeight}kg×".self::REP_RESET,
                ]);
                $set['weight_kg'] = $newWeight;
                $set['reps'] = self::REP_RESET;
            } else {
                $changes[] = __('app.progression.change', [
                    'exercise' => $title,
                    'from' => "{$weight}kg×{$reps}",
                    'to' => "{$weight}kg×".($reps + 1),
                ]);
                $set['reps'] = $reps + 1;
            }

            return $this->normalizeSet($set);
        }

        // Rep-range with no planned load → prescribe from recent e1RM, at the
        // bottom of the range with ~2 reps in reserve (loadForReps returns the
        // predicted MAX load for those reps; asking it for reps+2 leaves room).
        if (! $weight && $range && $e1rm) {
            $targetReps = (int) ($range['start'] ?? $range['end'] ?? 10);
            $prescribed = $this->roundToPlate(OneRepMax::loadForReps($e1rm, $targetReps + 2));
            if ($prescribed > 0) {
                $changes[] = __('app.progression.prescribe', [
                    'exercise' => $title,
                    'load' => "{$prescribed}kg",
                    'reps' => $targetReps,
                    'e1rm' => "{$e1rm}kg",
                ]);
                $set['weight_kg'] = $prescribed;
            }
        }

        return $this->normalizeSet($set);
    }

    /** Round to the nearest 0.5 kg (smallest common plate increment). */
    private function roundToPlate(float $weight): float
    {
        return round($weight * 2) / 2;
    }

    /**
     * ~7.5% off, rounded to a 2.5 kg plate step, always at least one step
     * down — a back-off that rounds back to the same bar is not one.
     */
    private function backoff(float $weight): float
    {
        $reduced = round($weight * self::BACKOFF_FACTOR / 2.5) * 2.5;

        return min($reduced, $weight - self::LOAD_STEP_KG);
    }

    /** Shape a set for the Hevy routine payload. */
    private function normalizeSet(array $set): array
    {
        $out = [
            'type' => SetType::fromRaw($set['type'] ?? null)->value,
            'weight_kg' => $set['weight_kg'] ?? null,
            'reps' => isset($set['reps']) ? (int) $set['reps'] : null,
            'distance_meters' => $set['distance_meters'] ?? null,
            'duration_seconds' => $set['duration_seconds'] ?? null,
            'custom_metric' => $set['custom_metric'] ?? null,
        ];

        if (! empty($set['rep_range'])) {
            $out['rep_range'] = $set['rep_range'];
        }

        return $out;
    }
}
