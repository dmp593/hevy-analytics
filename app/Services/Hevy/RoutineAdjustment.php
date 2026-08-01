<?php

namespace App\Services\Hevy;

use App\Models\ExerciseTemplate;
use App\Models\Routine;
use App\Models\RoutineExercise;

/**
 * Builds the Hevy routine.update payload for an advisor suggestion: add one
 * exercise, or swap one for a different stimulus. The rest of the routine
 * passes through untouched — these are SMALL adjustments by design.
 *
 * New movements get rep-range sets with no planned load: the athlete finds
 * the weight in the first session, and the progression engine prescribes from
 * their e1RM once logged sessions exist.
 *
 * Pure of side effects, like RoutineProgression: staging is the caller's job.
 */
class RoutineAdjustment
{
    private const NEW_EXERCISE_SETS = 2;

    private const MIN_SWAP_SETS = 2;

    /** @return array{payload: array<string, mixed>, changes: array<int, string>} */
    public function addExercise(Routine $routine, ExerciseTemplate $template): array
    {
        $routine->load('exercises');

        $exercises = $routine->exercises->map(fn ($ex) => $this->passthrough($ex))->all();

        $exercises[] = [
            'exercise_template_id' => $template->hevy_id,
            'superset_id' => null,
            'rest_seconds' => null,
            'notes' => null,
            'sets' => array_fill(0, self::NEW_EXERCISE_SETS, RoutinePayload::freshSet()),
        ];

        return [
            'payload' => $this->payload($routine, $exercises),
            'changes' => [__('app.advisor.change_add', [
                'exercise' => $template->title,
                'sets' => self::NEW_EXERCISE_SETS,
            ])],
        ];
    }

    /** @return array{payload: array<string, mixed>, changes: array<int, string>} */
    public function swapExercise(Routine $routine, RoutineExercise $old, ExerciseTemplate $new): array
    {
        $routine->load('exercises');

        // The new movement keeps the old one's slot, superset pairing, rest
        // and WORKING set count — the session's shape survives the swap. The
        // loads do not: strength does not transfer 1:1 between movements.
        $sets = max(self::MIN_SWAP_SETS, RoutinePayload::workingSetCount($old->sets ?? []));

        $exercises = $routine->exercises->map(function ($ex) use ($old, $new, $sets) {
            if ($ex->id !== $old->id) {
                return $this->passthrough($ex);
            }

            return [
                'exercise_template_id' => $new->hevy_id,
                'superset_id' => $ex->superset_id,
                'rest_seconds' => $ex->rest_seconds,
                'notes' => null,
                'sets' => array_fill(0, $sets, RoutinePayload::freshSet()),
            ];
        })->all();

        return [
            'payload' => $this->payload($routine, $exercises),
            'changes' => [__('app.advisor.change_swap', [
                'old' => $old->title,
                'new' => $new->title,
                'sets' => $sets,
            ])],
        ];
    }

    private function passthrough(RoutineExercise $ex): array
    {
        return [
            'exercise_template_id' => $ex->exercise_template_hevy_id,
            'superset_id' => $ex->superset_id,
            'rest_seconds' => $ex->rest_seconds,
            'notes' => $ex->notes,
            'sets' => array_map(RoutinePayload::normalizeSet(...), $ex->sets ?? []),
        ];
    }

    /** @param array<int, array<string, mixed>> $exercises */
    private function payload(Routine $routine, array $exercises): array
    {
        return [
            '_target_id' => $routine->hevy_id,
            'routine' => [
                'title' => $routine->title,
                'notes' => $routine->notes,
                'exercises' => $exercises,
            ],
        ];
    }
}
