<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The checker's job is to turn quiet misconfiguration into a sentence.
 *
 * The live call cannot be exercised against Paddle from CI, so the responses are
 * faked — which is enough, because what is being tested is how each answer is
 * INTERPRETED, and that is where the useful judgements are: a one-time price
 * looks perfectly valid until a subscription never appears.
 */
class BillingCheckCommandTest extends TestCase
{
    private function configured(array $overrides = []): void
    {
        config(array_merge([
            'cashier.sandbox' => true,
            'cashier.seller_id' => '123456',
            'cashier.client_side_token' => 'test_token',
            'cashier.api_key' => 'apikey_secret',
            'cashier.webhook_secret' => 'ntfset_secret',
            'cashier.currency' => 'EUR',
            'billing.price_id' => 'pri_01test',
            'billing.display_price' => '€8',
        ], $overrides));
    }

    private function priceResponse(array $overrides = []): array
    {
        return ['data' => array_merge([
            'id' => 'pri_01test',
            'description' => 'Monthly subscription',
            'unit_price' => ['amount' => '800', 'currency_code' => 'EUR'],
            'billing_cycle' => ['interval' => 'month', 'frequency' => 1],
        ], $overrides)];
    }

    public function test_it_fails_and_names_what_is_missing(): void
    {
        config(['cashier.seller_id' => null, 'cashier.api_key' => null, 'billing.price_id' => null]);

        $this->artisan('app:billing-check')
            ->expectsOutputToContain('PADDLE_SELLER_ID')
            ->expectsOutputToContain('PADDLE_PRICE_ID')
            ->assertFailed();
    }

    public function test_a_fully_configured_install_verifies_against_paddle(): void
    {
        $this->configured();
        Http::fake(['*' => Http::response($this->priceResponse())]);

        $this->artisan('app:billing-check')
            ->expectsOutputToContain('API key accepted')
            ->expectsOutputToContain('Monthly subscription')
            ->assertSuccessful();
    }

    /**
     * The failure this catches is the one that costs money: a one-time price
     * takes the payment and never creates a subscription, so the app waits for
     * a subscription.created that is never coming.
     */
    public function test_a_one_time_price_is_rejected(): void
    {
        $this->configured();
        Http::fake(['*' => Http::response($this->priceResponse(['billing_cycle' => null]))]);

        $this->artisan('app:billing-check')
            ->expectsOutputToContain('one-time, not recurring')
            ->assertFailed();
    }

    /** Sandbox and live keys are indistinguishable by eye; Paddle is not fooled. */
    public function test_a_rejected_key_is_reported_as_such(): void
    {
        $this->configured();
        Http::fake(['*' => Http::response(['error' => ['detail' => 'Forbidden']], 403)]);

        $this->artisan('app:billing-check')
            ->expectsOutputToContain('Could not verify with Paddle')
            ->expectsOutputToContain('belongs to the other environment')
            ->assertFailed();
    }

    public function test_a_currency_mismatch_is_warned_about(): void
    {
        $this->configured(['cashier.currency' => 'USD']);
        Http::fake(['*' => Http::response($this->priceResponse())]);

        $this->artisan('app:billing-check')
            ->expectsOutputToContain('CASHIER_CURRENCY')
            ->assertSuccessful();
    }

    public function test_offline_skips_the_network_call(): void
    {
        $this->configured();
        Http::fake();

        $this->artisan('app:billing-check --offline')->assertSuccessful();

        Http::assertNothingSent();
    }

    /** The output gets pasted into chat threads, so it must not carry secrets. */
    public function test_the_output_never_echoes_a_secret(): void
    {
        $this->configured();
        Http::fake(['*' => Http::response($this->priceResponse())]);

        $this->artisan('app:billing-check')
            ->doesntExpectOutputToContain('apikey_secret')
            ->doesntExpectOutputToContain('ntfset_secret')
            ->assertSuccessful();
    }
}
