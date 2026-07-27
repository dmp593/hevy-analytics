<?php

namespace App\Support;

use App\Models\User;

/**
 * Where a new account is on the road from "registered" to "seeing analysis".
 *
 * The old first-run experience was a single "set up profile" button, which
 * told a new user the first step and then abandoned them: key pasted, back on
 * the dashboard, still empty, no hint that a sync had to happen or that FFMI
 * needs a height. This names every step, in order, and knows which are done —
 * so the dashboard can show a checklist that shrinks instead of a wall that
 * looks broken.
 */
class Onboarding
{
    /** @param array<int, array{key: string, done: bool, route: string}> $steps */
    private function __construct(public readonly array $steps) {}

    public static function for(User $user): self
    {
        // Two doors into the app now: the API key (Hevy Pro) and the CSV
        // import (any Hevy account). Either one satisfies the data steps — a
        // CSV-only athlete must not be shown a checklist that can never finish.
        $hasWorkouts = $user->workouts()->exists();

        return new self([
            [
                'key' => 'hevy_key',
                'done' => $user->hasHevyKey() || $hasWorkouts,
                'route' => route('profile.edit'),
            ],
            [
                // The first sync is queued automatically once the key exists,
                // but it still needs a worker and a moment — naming it tells
                // the user what "still empty" means in the meantime.
                'key' => 'sync',
                'done' => $user->hevy_last_synced_at !== null || $hasWorkouts,
                'route' => route('dashboard'),
            ],
            [
                'key' => 'profile',
                'done' => $user->sex !== null && $user->age !== null && $user->height_cm !== null,
                'route' => route('profile.edit'),
            ],
            [
                'key' => 'goal',
                'done' => $user->activeGoal() !== null,
                'route' => route('goals'),
            ],
        ]);
    }

    public function complete(): bool
    {
        return collect($this->steps)->every(fn ($s) => $s['done']);
    }

    public function doneCount(): int
    {
        return collect($this->steps)->where('done', true)->count();
    }
}
