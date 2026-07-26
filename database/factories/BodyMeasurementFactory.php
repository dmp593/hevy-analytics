<?php

namespace Database\Factories;

use App\Models\BodyMeasurement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BodyMeasurement>
 */
class BodyMeasurementFactory extends Factory
{
    protected $model = BodyMeasurement::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->unique()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'weight_kg' => fake()->randomFloat(1, 60, 100),
            'fat_percent' => fake()->randomFloat(1, 8, 25),
            'neck_cm' => 39.0,
            'abdomen' => fake()->randomFloat(1, 75, 95),
            'waist' => fake()->randomFloat(1, 72, 92),
        ];
    }
}
