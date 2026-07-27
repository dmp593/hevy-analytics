<?php

namespace Database\Factories;

use App\Models\ExerciseTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseTemplate>
 */
class ExerciseTemplateFactory extends Factory
{
    protected $model = ExerciseTemplate::class;

    /** Real Hevy-style exercises, so tests read like real training. */
    private const CATALOGUE = [
        ['Bench Press (Barbell)', 'chest', ['triceps', 'shoulders'], 'barbell'],
        ['Squat (Barbell)', 'quadriceps', ['glutes', 'hamstrings'], 'barbell'],
        ['Deadlift (Barbell)', 'lower_back', ['hamstrings', 'glutes'], 'barbell'],
        ['Overhead Press (Barbell)', 'shoulders', ['triceps'], 'barbell'],
        ['Barbell Row', 'upper_back', ['biceps'], 'barbell'],
        ['Lat Pulldown (Cable)', 'lats', ['biceps'], 'cable'],
        ['Bicep Curl (Dumbbell)', 'biceps', [], 'dumbbell'],
        ['Leg Curl (Machine)', 'hamstrings', [], 'machine'],
    ];

    public function definition(): array
    {
        [$title, $primary, $secondary, $equipment] = fake()->randomElement(self::CATALOGUE);

        return [
            'user_id' => User::factory(),
            'hevy_id' => fake()->unique()->uuid(),
            'title' => $title,
            'type' => 'weight_reps',
            'primary_muscle_group' => $primary,
            'secondary_muscle_groups' => $secondary,
            'equipment' => $equipment,
            'is_custom' => false,
        ];
    }

    /** A specific exercise rather than a random one. */
    public function named(string $title, string $primary, array $secondary = []): static
    {
        return $this->state(fn () => [
            'title' => $title,
            'primary_muscle_group' => $primary,
            'secondary_muscle_groups' => $secondary,
        ]);
    }
}
