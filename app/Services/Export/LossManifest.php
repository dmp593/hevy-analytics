<?php

namespace App\Services\Export;

/**
 * What a conversion will lose, counted from the person's actual rows.
 *
 * Every target dialect carries a different subset of the model. Saying so in
 * the abstract ("some fields may not survive") is a disclaimer; saying "the
 * RPE on 214 sets" is information someone can weigh before they commit. The
 * counts come from the same normalised structure the writer will receive, so
 * the manifest can never disagree with the file.
 */
class LossManifest
{
    /**
     * What each target dialect can carry. 'set_types' distinguishes Strong,
     * whose only marker is a "W" set order for warmups — failure and drop
     * sets flatten to normal there.
     */
    private const CAPABILITIES = [
        'hevy' => ['time' => true, 'title' => true, 'set_types' => 'all', 'rpe' => true, 'notes' => true, 'supersets' => true, 'cardio' => true],
        'strong' => ['time' => true, 'title' => true, 'set_types' => 'warmup', 'rpe' => true, 'notes' => true, 'supersets' => false, 'cardio' => true],
        'fitnotes' => ['time' => false, 'title' => false, 'set_types' => 'none', 'rpe' => false, 'notes' => false, 'supersets' => false, 'cardio' => true],
        'jefit' => ['time' => false, 'title' => false, 'set_types' => 'none', 'rpe' => false, 'notes' => false, 'supersets' => false, 'cardio' => false],
    ];

    /**
     * @param  array<int, array>  $workouts  the normalised structure
     * @return array<int, array{key: string, count: int}> losses, worst first
     */
    public static function for(array $workouts, string $target): array
    {
        $cap = self::CAPABILITIES[$target];

        $workoutCount = count($workouts);
        $titled = 0;
        $described = 0;
        $notes = 0;
        $supersets = 0;
        $rpe = 0;
        $types = 0;
        $cardio = 0;

        foreach ($workouts as $w) {
            if (($w['title'] ?? 'Workout') !== 'Workout') {
                $titled++;
            }

            if (! empty($w['description'])) {
                $described++;
            }

            foreach ($w['exercises'] as $ex) {
                if (! empty($ex['notes'])) {
                    $notes++;
                }

                if (($ex['superset_id'] ?? null) !== null) {
                    $supersets++;
                }

                foreach ($ex['sets'] as $set) {
                    if (($set['rpe'] ?? null) !== null) {
                        $rpe++;
                    }

                    $type = $set['type'] ?? 'normal';

                    if ($cap['set_types'] === 'none' && $type !== 'normal') {
                        $types++;
                    } elseif ($cap['set_types'] === 'warmup' && ! in_array($type, ['normal', 'warmup'], true)) {
                        $types++;
                    }

                    if (($set['distance_meters'] ?? null) !== null || ($set['duration_seconds'] ?? null) !== null) {
                        $cardio++;
                    }
                }
            }
        }

        $losses = [
            ['key' => 'time', 'count' => $cap['time'] ? 0 : $workoutCount],
            ['key' => 'title', 'count' => $cap['title'] ? 0 : $titled],
            ['key' => 'set_types', 'count' => $types],
            ['key' => 'rpe', 'count' => $cap['rpe'] ? 0 : $rpe],
            ['key' => 'notes', 'count' => $cap['notes'] ? 0 : $notes + $described],
            ['key' => 'supersets', 'count' => $cap['supersets'] ? 0 : $supersets],
            ['key' => 'cardio', 'count' => $cap['cardio'] ? 0 : $cardio],
        ];

        return array_values(array_filter($losses, fn ($l) => $l['count'] > 0));
    }
}
