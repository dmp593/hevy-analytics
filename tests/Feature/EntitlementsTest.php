<?php

namespace Tests\Feature;

use App\Billing\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Paddle\Subscription;
use Tests\Support\SeedsTrainingData;
use Tests\Support\Subscribes;
use Tests\TestCase;

/**
 * The wall.
 *
 * The free tier is capped on HISTORY DEPTH, not on which pages exist. These
 * tests pin both halves of that: that the cap is real and cannot be widened by
 * asking, and that it never removes an athlete's current numbers or their right
 * to their own data.
 */
class EntitlementsTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData, Subscribes;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-17 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------- states

    public function test_a_new_registration_starts_on_a_card_free_trial(): void
    {
        $this->post('/register', [
            'name' => 'New Athlete',
            'email' => 'new@example.test',
            'password' => 'correct-horse-9-battery',
            'password_confirmation' => 'correct-horse-9-battery',
        ])->assertRedirect();

        $user = User::where('email', 'new@example.test')->firstOrFail();

        $this->assertSame(State::Trialing, $user->billingState());
        $this->assertTrue($user->entitlements()->historyDays() === null);
        // Card-free means Paddle has not been told anything about them.
        $this->assertNull($user->customer);
    }

    public function test_an_expired_trial_without_a_subscription_falls_to_free(): void
    {
        $user = User::factory()->free()->create();

        $this->assertSame(State::Free, $user->billingState());
        $this->assertSame(30, $user->entitlements()->historyDays());
    }

    public function test_an_active_subscription_grants_full_access(): void
    {
        $user = User::factory()->free()->create();
        $this->subscribe($user);

        $this->assertSame(State::Subscribed, $user->fresh()->billingState());
        $this->assertNull($user->fresh()->entitlements()->historyDays());
    }

    /**
     * Cancelled but inside the period already paid for. Cutting access at the
     * moment of cancellation would be taking money for time not served.
     */
    public function test_a_cancelled_subscription_keeps_access_until_the_period_ends(): void
    {
        $user = User::factory()->free()->create();
        $this->subscribeOnGracePeriod($user);

        $this->assertSame(State::GracePeriod, $user->fresh()->billingState());
        $this->assertNull($user->fresh()->entitlements()->historyDays());
    }

    /**
     * A failing card is a billing problem, not a reason to confiscate the
     * product mid-period — that turns a hiccup into a cancellation.
     */
    public function test_a_past_due_subscription_keeps_access(): void
    {
        $user = User::factory()->free()->create();
        $this->subscribePastDue($user);

        $this->assertSame(State::PastDue, $user->fresh()->billingState());
        $this->assertNull($user->fresh()->entitlements()->historyDays());
    }

    public function test_subscribing_during_a_trial_reports_as_subscribed(): void
    {
        $user = User::factory()->trialing(10)->create();
        $this->subscribe($user);

        $this->assertSame(State::Subscribed, $user->fresh()->billingState());
    }

    // ------------------------------------------------------------- the wall

    public function test_training_history_beyond_the_cap_is_not_returned_to_a_free_athlete(): void
    {
        $user = User::factory()->free()->create();
        $this->seedExerciseTemplates($user, ['bench' => ['Bench Press', 'chest', []]]);

        // One session inside the 30-day cap, one well outside it.
        $this->seedWorkout($user, Carbon::now()->subDays(5)->setTime(18, 0), ['bench'], setsPerExercise: 4);
        $this->seedWorkout($user, Carbon::now()->subDays(200)->setTime(18, 0), ['bench'], setsPerExercise: 4);

        $filter = new \App\Services\Analytics\FilterCriteria(
            from: Carbon::now()->subYears(2),
            to: Carbon::now(),
        );

        $sets = (new \App\Services\Analytics\SetQuery($user, $filter))->rows();

        $this->assertCount(4, $sets, 'only the session inside the cap should be visible');
    }

    public function test_the_same_athlete_sees_everything_once_subscribed(): void
    {
        $user = User::factory()->free()->create();
        $this->seedExerciseTemplates($user, ['bench' => ['Bench Press', 'chest', []]]);
        $this->seedWorkout($user, Carbon::now()->subDays(5)->setTime(18, 0), ['bench'], setsPerExercise: 4);
        $this->seedWorkout($user, Carbon::now()->subDays(200)->setTime(18, 0), ['bench'], setsPerExercise: 4);

        $this->subscribe($user);
        $user = $user->fresh();

        $filter = new \App\Services\Analytics\FilterCriteria(
            from: Carbon::now()->subYears(2),
            to: Carbon::now(),
        );

        $this->assertCount(8, (new \App\Services\Analytics\SetQuery($user, $filter))->rows());
    }

    /** The cap narrows a window; it must never widen a narrower one. */
    public function test_a_request_for_less_than_the_cap_is_left_alone(): void
    {
        $user = User::factory()->free()->create();
        $requested = Carbon::now()->subDays(7);

        $this->assertTrue(
            $user->entitlements()->clampFrom($requested)->equalTo($requested),
        );
    }

    public function test_an_open_ended_request_is_clamped_to_the_cap(): void
    {
        $user = User::factory()->free()->create();

        $clamped = $user->entitlements()->clampFrom(null);

        $this->assertNotNull($clamped);
        $this->assertSame(
            Carbon::now()->subDays(30)->startOfDay()->toDateString(),
            $clamped->toDateString(),
        );
    }

    public function test_body_history_is_capped_but_current_numbers_are_not(): void
    {
        $user = User::factory()->free()->create(['height_cm' => 178, 'age' => 30, 'sex' => 'male']);

        $user->bodyMeasurements()->create(['date' => Carbon::now()->subDays(2), 'weight_kg' => 80]);
        $user->bodyMeasurements()->create(['date' => Carbon::now()->subDays(300), 'weight_kg' => 95]);

        $analytics = new \App\Services\Analytics\BodyCompAnalytics($user);

        // History is capped…
        $this->assertCount(1, $analytics->series('weight_kg', Carbon::now()->subYears(2)));

        // …but "what do I weigh now" still works. Capping that would be
        // withholding a number the athlete typed in themselves.
        $this->assertSame(80.0, $analytics->status()['weight_kg']);
    }

    // ------------------------------------------------------------ never gated

    /**
     * GDPR Article 20 is a right, not a feature. Holding someone's own training
     * history behind a paywall would be both unlawful and a nasty thing to do.
     */
    public function test_data_export_is_available_on_every_tier(): void
    {
        foreach ([User::factory()->free(), User::factory()->trialing()] as $factory) {
            $user = $factory->create();

            $this->assertTrue($user->entitlements()->canExportData());
            $this->actingAs($user)->get('/settings/export')->assertOk();
        }
    }

    public function test_every_page_still_loads_on_the_free_tier(): void
    {
        $user = User::factory()->free()->create([
            'hevy_api_key' => '00000000-0000-0000-0000-000000000000',
            'sex' => 'male', 'age' => 30, 'height_cm' => 178, 'activity_level' => 1.55,
        ]);
        $user->goals()->create(['type' => 'lean_bulk', 'is_active' => true, 'started_at' => now()]);

        // The wall is depth of history, not which pages exist. A locked door
        // where a number used to be is a worse product than a shorter window.
        foreach (['/dashboard', '/body', '/muscle', '/nutrition', '/performance',
            '/projections', '/routines', '/strength-levels', '/goals', '/photos', '/ai'] as $path) {
            $this->actingAs($user)->get($path)->assertOk();
        }
    }

    // -------------------------------------------------------------------- ai

    public function test_the_free_tier_has_no_included_ai_allowance(): void
    {
        $user = User::factory()->free()->create();

        $this->assertSame(0, $user->entitlements()->aiAnalysesPerMonth());
        $this->assertFalse($user->entitlements()->canUseAi());
    }

    /** Their own key is their own money, so the plan has no say in it. */
    public function test_a_free_athlete_with_their_own_key_may_still_use_ai(): void
    {
        $user = User::factory()->free()->create();
        $user->aiCredentials()->create([
            'provider' => \App\Services\AI\ProviderRegistry::OPENAI,
            'api_key' => 'sk-their-own',
            'model' => 'gpt-4o',
            'base_url' => 'https://api.openai.com/v1',
            'is_active' => true,
        ]);

        $this->assertTrue($user->entitlements()->canUseAi());
    }

    public function test_a_subscriber_gets_the_configured_allowance(): void
    {
        config(['billing.entitlements.subscribed.ai_analyses_per_month' => 30]);

        $user = User::factory()->free()->create();
        $this->subscribe($user);

        $this->assertSame(30, $user->fresh()->entitlements()->aiAnalysesPerMonth());
        $this->assertTrue($user->fresh()->entitlements()->canUseAi());
    }

    public function test_state_labels_all_resolve(): void
    {
        foreach (State::cases() as $state) {
            $this->assertNotSame('', trim($state->label()));
            $this->assertStringNotContainsString('app.billing', $state->label());
        }
    }
}
