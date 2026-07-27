<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DataConfidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The account-wide "your numbers are still settling" notice. Its job is to be
 * present exactly while it is true: shown too long it is wallpaper, hidden too
 * early the app's first impression is numbers that swing like a bug.
 */
class DataConfidenceTest extends TestCase
{
    use RefreshDatabase;

    private function withWorkouts(User $user, int $count, int $spanDays): void
    {
        foreach (range(0, $count - 1) as $i) {
            $user->workouts()->create([
                'hevy_id' => "w{$i}",
                'title' => 'Session',
                'start_time' => now()->subDays($spanDays)->addDays($count > 1 ? $spanDays * $i / ($count - 1) : 0),
            ]);
        }
    }

    public function test_young_data_is_flagged_on_the_dashboard(): void
    {
        $user = User::factory()->create(['hevy_api_key' => '00000000-0000-0000-0000-000000000000']);
        $this->withWorkouts($user, 4, 7);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee(__('app.confidence.title'));
    }

    public function test_enough_sessions_across_enough_weeks_clears_the_flag(): void
    {
        $user = User::factory()->create(['hevy_api_key' => '00000000-0000-0000-0000-000000000000']);
        $this->withWorkouts($user, DataConfidence::MIN_SESSIONS, DataConfidence::MIN_SPAN_DAYS + 7);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertDontSee(__('app.confidence.title'));
    }

    /** Many sessions crammed into one week are still not a trend. */
    public function test_a_dense_single_week_is_still_young(): void
    {
        $user = User::factory()->create();
        $this->withWorkouts($user, 12, 6);

        $confidence = DataConfidence::for($user->fresh());

        $this->assertSame(12, $confidence->sessions);
        $this->assertFalse($confidence->established());
    }

    public function test_an_empty_account_shows_setup_not_the_confidence_note(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertDontSee(__('app.confidence.title'));
    }
}
