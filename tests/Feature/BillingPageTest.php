<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Subscribes;
use Tests\TestCase;

class BillingPageTest extends TestCase
{
    use RefreshDatabase, Subscribes;

    public function test_the_billing_page_loads_in_every_state(): void
    {
        $cases = [
            'trialing' => fn () => User::factory()->trialing(5)->create(),
            'free' => fn () => User::factory()->free()->create(),
            'subscribed' => function () {
                $user = User::factory()->free()->create();
                $this->subscribe($user);

                return $user->fresh();
            },
            'grace' => function () {
                $user = User::factory()->free()->create();
                $this->subscribeOnGracePeriod($user);

                return $user->fresh();
            },
            'past due' => function () {
                $user = User::factory()->free()->create();
                $this->subscribePastDue($user);

                return $user->fresh();
            },
        ];

        foreach ($cases as $name => $make) {
            $this->actingAs($make())->get('/billing')
                ->assertOk()
                ->assertSee(__('app.billing.title'));
        }
    }

    /**
     * A trial with no card must say so. Someone who signed up expecting to be
     * charged, or expecting not to be, deserves to know which it is.
     */
    public function test_a_trialing_athlete_is_told_no_card_is_on_file(): void
    {
        $user = User::factory()->trialing(5)->create();

        $this->actingAs($user)->get('/billing')
            ->assertOk()
            ->assertSee('No card is on file', escape: false);
    }

    /** The pitch must include what the product does not do. */
    public function test_the_subscribe_pitch_states_its_limits(): void
    {
        $this->actingAs(User::factory()->free()->create())->get('/billing')
            ->assertOk()
            ->assertSee(__('app.billing.limits_title'))
            ->assertSee('does not write your programme', escape: false);
    }

    public function test_a_subscriber_is_not_shown_a_sales_pitch(): void
    {
        $user = User::factory()->free()->create();
        $this->subscribe($user);

        $this->actingAs($user->fresh())->get('/billing')
            ->assertOk()
            ->assertDontSee(__('app.billing.what_you_get'));
    }

    /**
     * Cancelling ends the subscription at the period boundary, never
     * immediately: they paid for the rest of it.
     */
    public function test_cancelling_keeps_access_until_the_period_ends(): void
    {
        $user = User::factory()->free()->create();
        $subscription = $this->subscribe($user);

        // Cashier's cancel() calls Paddle, so drive the state directly and
        // assert on what the app does with it.
        $subscription->update(['ends_at' => now()->addDays(12)]);

        $this->assertTrue($user->fresh()->subscription()->onGracePeriod());
        $this->assertNull($user->fresh()->entitlements()->historyDays());
    }

    public function test_cancel_and_resume_require_an_appropriate_subscription(): void
    {
        $free = User::factory()->free()->create();

        $this->actingAs($free)->post('/billing/cancel')->assertNotFound();
        $this->actingAs($free)->post('/billing/resume')->assertNotFound();
    }

    public function test_billing_requires_authentication(): void
    {
        $this->get('/billing')->assertRedirect('/login');
        $this->post('/billing/cancel')->assertRedirect('/login');
    }

    /**
     * The page must still render when Paddle is unreachable or unconfigured.
     * Someone checking their renewal date should not meet a 500 because a
     * third party is having an outage.
     */
    public function test_the_page_survives_billing_being_unconfigured(): void
    {
        config(['billing.price_id' => null]);

        $this->actingAs(User::factory()->free()->create())->get('/billing')
            ->assertOk()
            ->assertSee(__('app.billing.unavailable'));
    }

    /**
     * Cashier only attaches its signature-verification middleware when a
     * webhook secret is configured. Without one the endpoint is open, and
     * anyone who knows the URL can grant themselves a subscription — so the app
     * refuses to boot in production rather than serving it.
     */
    public function test_production_refuses_to_boot_without_a_webhook_secret(): void
    {
        config(['cashier.webhook_secret' => null, 'billing.price_id' => 'pri_live']);

        $provider = new AppServiceProvider($this->app);

        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/PADDLE_WEBHOOK_SECRET/');

        $provider->boot();
    }

    /** An install with no billing configured at all is a legitimate deployment. */
    public function test_production_boots_without_billing_configured(): void
    {
        config(['cashier.webhook_secret' => null, 'billing.price_id' => null]);

        $this->app->detectEnvironment(fn () => 'production');

        $provider = new AppServiceProvider($this->app);
        $provider->boot();

        $this->assertTrue(true, 'boot completed without throwing');
    }
}
