<?php

namespace Tests\Feature;

use App\Mail\WeeklyCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

/**
 * The Monday check-in email: watermarked idempotency, honest eligibility
 * (data, opt-in, not the demo), and content assembled from the same
 * services the dashboard reads.
 */
class WeeklyCheckinTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    private function trainedUser(): User
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);
        $this->seedWorkout($user, Carbon::now()->subDays(2), ['sq'], 3);

        return $user;
    }

    public function test_the_command_emails_once_and_stamps_the_watermark(): void
    {
        Mail::fake();
        $user = $this->trainedUser();

        $this->artisan('app:send-weekly-checkins')->assertSuccessful();
        $this->artisan('app:send-weekly-checkins')->assertSuccessful();

        Mail::assertSent(WeeklyCheckin::class, 1);
        $this->assertNotNull($user->fresh()->weekly_checkin_sent_at);
    }

    public function test_opt_out_demo_and_empty_accounts_are_left_alone(): void
    {
        Mail::fake();

        $optedOut = $this->trainedUser();
        $optedOut->forceFill(['weekly_email' => false])->save();

        $demo = $this->trainedUser();
        $demo->forceFill(['is_demo' => true])->save();

        User::factory()->create(); // no workouts, nothing to summarise

        $this->artisan('app:send-weekly-checkins')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_stale_watermark_earns_a_fresh_send(): void
    {
        Mail::fake();
        $user = $this->trainedUser();
        $user->forceFill(['weekly_checkin_sent_at' => now()->subDays(7)])->save();

        $this->artisan('app:send-weekly-checkins')->assertSuccessful();

        Mail::assertSent(WeeklyCheckin::class, 1);
    }

    public function test_the_email_renders_with_the_dashboards_numbers(): void
    {
        $user = $this->trainedUser();
        $this->seedWeightTrend($user, 80.0, 0.0, 5);

        $html = (new WeeklyCheckin($user))->render();

        $this->assertStringContainsString('80', $html);
        $this->assertStringContainsString(route('dashboard'), $html);
    }

    public function test_dry_run_sends_nothing(): void
    {
        Mail::fake();
        $this->trainedUser();

        $this->artisan('app:send-weekly-checkins --dry-run')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull(User::first()->weekly_checkin_sent_at);
    }

    public function test_the_profile_toggle_switches_the_email_off(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/profile/emails', ['weekly_email' => '0'])
            ->assertRedirect(route('profile.edit'));

        $this->assertFalse($user->fresh()->weekly_email);
    }
}
