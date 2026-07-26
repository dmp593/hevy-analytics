<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workout>
 */
class WorkoutFactory extends Factory
{
    protected $model = Workout::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'user_id' => User::factory(),
            'hevy_id' => fake()->unique()->uuid(),
            'title' => fake()->randomElement(['Push', 'Pull', 'Legs', 'Upper', 'Lower', 'Full Body']),
            'start_time' => $start,
            'end_time' => (clone $start)->modify('+75 minutes'),
        ];
    }

    public function on(\DateTimeInterface $date): static
    {
        return $this->state(fn () => [
            'start_time' => $date,
            'end_time' => (clone $date)->modify('+75 minutes'),
        ]);
    }
}
