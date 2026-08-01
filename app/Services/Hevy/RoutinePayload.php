<?php

namespace App\Services\Hevy;

use App\Enums\SetType;

/**
 * Shapes routine data for the Hevy routine.update payload. Shared by every
 * service that stages a routine write (progression, adjustments), so the
 * payload shape can never fork between them.
 */
class RoutinePayload
{
    /** Shape a set for the Hevy routine payload. */
    public static function normalizeSet(array $set): array
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

    /**
     * A fresh working set for a movement with no history: a rep range and no
     * planned load. The progression engine prescribes the load from e1RM once
     * sessions exist — inventing a starting weight would be a guess, and this
     * app does not guess.
     */
    public static function freshSet(int $repStart = 8, int $repEnd = 12): array
    {
        return [
            'type' => 'normal',
            'weight_kg' => null,
            'reps' => null,
            'distance_meters' => null,
            'duration_seconds' => null,
            'custom_metric' => null,
            'rep_range' => ['start' => $repStart, 'end' => $repEnd],
        ];
    }

    /** How many working (non-warm-up) sets an exercise's set list holds. */
    public static function workingSetCount(array $sets): int
    {
        return count(array_filter(
            $sets,
            fn ($set) => SetType::fromRaw($set['type'] ?? null)->isWorking()
        ));
    }
}
