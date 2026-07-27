<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Matches what registration does. A factory user represents someone
            // who signed up, and everyone who signs up gets the trial — without
            // this, every test silently ran against the free tier's 30-day
            // history cap and any test seeding older data broke for reasons that
            // had nothing to do with what it was testing.
            'trial_ends_at' => now()->addDays((int) config('billing.trial_days', 14)),
        ];
    }

    /** Trial expired, never subscribed: the free tier. */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->subDay(),
        ]);
    }

    /** Mid-trial, with the trial ending on a known day. */
    public function trialing(int $daysLeft = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'trial_ends_at' => now()->addDays($daysLeft),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
