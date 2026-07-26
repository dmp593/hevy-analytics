<?php

namespace Database\Factories;

use App\Models\WorkoutExercise;
use App\Models\WorkoutSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutSet>
 */
class WorkoutSetFactory extends Factory
{
    protected $model = WorkoutSet::class;

    public function definition(): array
    {
        return [
            'workout_exercise_id' => WorkoutExercise::factory(),
            'index' => 0,
            'type' => 'normal',
            'weight_kg' => fake()->numberBetween(40, 140),
            'reps' => fake()->numberBetween(3, 12),
            'rpe' => fake()->randomElement([null, 7, 7.5, 8, 8.5, 9]),
        ];
    }

    /** Warm-ups are excluded from every hard-set calculation. */
    public function warmup(): static
    {
        return $this->state(fn () => ['type' => 'warmup']);
    }
}
