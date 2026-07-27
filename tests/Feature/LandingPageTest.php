<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The landing page is the only page that makes promises before anyone can check
 * them, so these tests are mostly about it not being able to drift away from
 * what the app actually enforces.
 *
 * Where a number appears on the page it must come from config, not from copy —
 * a hardcoded "30 days" in a sentence survives every change to the entitlement
 * it describes, and quietly becomes a false statement about a paid product.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_sees_the_pitch_rather_than_the_login_form(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(__('app.landing.headline'))
            ->assertSee(route('register'))
            ->assertSee(__('app.landing.cta', ['days' => config('billing.trial_days')]));
    }

    public function test_a_signed_in_athlete_is_taken_to_their_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
    }

    /**
     * The page tells people they need a Hevy key before they sign up. Finding
     * that out afterwards, on an empty dashboard, is the complaint this
     * prevents — so it has to be on the page and not merely in the docs.
     */
    public function test_the_hevy_requirement_is_stated_before_signup(): void
    {
        $this->get('/')->assertSee('hevy.com', escape: false);
    }

    public function test_the_advertised_price_and_trial_come_from_config(): void
    {
        config([
            'billing.display_price' => '€11',
            'billing.trial_days' => 21,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('€11')
            ->assertSee(__('app.landing.cta', ['days' => 21]));
    }

    /**
     * The one that matters most: the free tier's advertised history cap is the
     * cap that Entitlements actually applies. If someone changes the config, the
     * sentence follows it.
     */
    public function test_the_advertised_free_history_matches_the_entitlement_enforced(): void
    {
        config(['billing.entitlements.free.history_days' => 45]);

        $this->get('/')
            ->assertOk()
            ->assertSee(__('app.landing.free_history', ['days' => 45]))
            ->assertDontSee(__('app.landing.free_history', ['days' => 30]));

        // And the number on the page is the number an actual free account gets.
        $this->assertSame(45, User::factory()->free()->create()->entitlements()->historyDays());
    }

    public function test_the_advertised_ai_allowance_matches_what_a_subscriber_gets(): void
    {
        config(['billing.entitlements.subscribed.ai_analyses_per_month' => 42]);

        $this->get('/')->assertOk()->assertSee(__('app.landing.paid_ai', ['count' => 42]));
    }

    /**
     * "No card asked for" has to survive contact with the billing provider.
     * Registration must not touch Paddle at all — Cashier's own generic trial
     * would, because it lives on a customer record it has to create first.
     */
    public function test_registering_starts_the_trial_without_contacting_paddle(): void
    {
        Http::preventStrayRequests();

        $this->post('/register', [
            'name' => 'New Athlete',
            'email' => 'new@example.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $user = User::where('email', 'new@example.test')->firstOrFail();

        $this->assertTrue($user->trial_ends_at?->isFuture());
        $this->assertSame(
            (int) config('billing.trial_days'),
            (int) round(now()->diffInDays($user->trial_ends_at, false)),
        );
        $this->assertNull($user->customer);
    }

    /** Every limit stated on the page is stated on the page, not in a tooltip. */
    public function test_the_limits_section_is_actually_rendered(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (['logging', 'scale', 'projections', 'landmarks', 'ai', 'medical'] as $limit) {
            $response->assertSee(__("app.landing.limit_{$limit}"));
        }
    }

    /**
     * The sample verdicts are rendered from the product's own language keys, so
     * that rewording a verdict cannot leave the sales page quoting a sentence
     * the app no longer says.
     */
    public function test_the_samples_quote_the_apps_own_wording(): void
    {
        $this->get('/')
            ->assertSee(__('app.body.verdict.gaining_both'))
            ->assertSee(__('app.nutrition.verdict.higher_headline'));
    }

    public function test_it_renders_in_every_shipped_language(): void
    {
        foreach (\App\Support\Locales::codes() as $locale) {
            $this->post("/locale/{$locale}");

            $this->get('/')
                ->assertOk()
                // A missing key renders as the dotted path itself, which is the
                // one failure a human reviewing a translated page always misses.
                ->assertDontSee('app.landing.');
        }
    }
}
