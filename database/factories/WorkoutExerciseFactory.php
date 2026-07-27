<?php

namespace Database\Factories;

use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkoutExercise>
 */
class WorkoutExerciseFactory extends Factory
{
    protected $model = WorkoutExercise::class;

    public function definition(): array
    {
        return [
            'workout_id' => Workout::factory(),
            'index' => 0,
            'title' => fake()->randomElement(['Bench Press (Barbell)', 'Squat (Barbell)', 'Barbell Row']),
            'exercise_template_hevy_id' => fake()->uuid(),
        ];
    }
}
