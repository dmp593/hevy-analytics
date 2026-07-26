<?php

namespace App\Services\Hevy;

use App\Enums\SetType;
use App\Models\Routine;
use App\Models\User;
use App\Science\Strength\OneRepMax;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\StrengthAnalytics;
use Illuminate\Support\Carbon;

/**
 * Builds an evidence-based "next session" progression for a routine using
 * double progression, informed by each exercise's recent best e1RM.
 *
 * Rules per working set:
 *  - Fixed weight×reps: add a rep until 12, then +2.5 kg and reset to 8 reps.
 *  - Rep-range only (no load): prescribe a load from recent e1RM at the top of
 *    the range with a small (+2%) progressive-overload bump.
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

    private const OVERLOAD_FACTOR = 1.02;

    public function __construct(private readonly User $user) {}

    /**
     * @return array{payload: array<string,mixed>, changes: array<int,string>}
     */
    public function build(Routine $routine): array
    {
        $changes = [];
        $exercises = [];

        foreach ($routine->load('exercises')->exercises as $exercise) {
            $e1rm = $this->recentBestE1rm($exercise->exercise_template_hevy_id);

            $sets = [];
            foreach ($exercise->sets ?? [] as $set) {
                $sets[] = $this->progressSet($set, $exercise->title, $e1rm, $changes);
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
     * @param  array<int, string>  $changes  (mutated by reference)
     */
    private function progressSet(array $set, ?string $title, ?float $e1rm, array &$changes): array
    {
        $type = SetType::fromRaw($set['type'] ?? null);

        if (! $type->isWorking()) {
            return $this->normalizeSet($set);
        }

        $weight = $set['weight_kg'] ?? null;
        $reps = $set['reps'] ?? null;
        $range = $set['rep_range'] ?? null;

        // Fixed weight × reps → double progression.
        if ($weight && $reps) {
            if ($reps >= self::REP_CAP) {
                $newWeight = $this->roundToPlate($weight + self::LOAD_STEP_KG);
                $changes[] = "{$title}: {$weight}kg×{$reps} → {$newWeight}kg×".self::REP_RESET;
                $set['weight_kg'] = $newWeight;
                $set['reps'] = self::REP_RESET;
            } else {
                $changes[] = "{$title}: {$weight}kg×{$reps} → {$weight}kg×".($reps + 1);
                $set['reps'] = $reps + 1;
            }

            return $this->normalizeSet($set);
        }

        // Rep-range target with no load → prescribe a load from recent e1RM.
        if ($range && $e1rm) {
            $targetReps = (int) ($range['end'] ?? $range['start'] ?? 10);
            $prescribed = $this->roundToPlate(OneRepMax::loadForReps($e1rm, $targetReps) * self::OVERLOAD_FACTOR);
            if ($prescribed > 0) {
                $changes[] = "{$title}: prescribe {$prescribed}kg × {$targetReps} (from e1RM {$e1rm}kg)";
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
