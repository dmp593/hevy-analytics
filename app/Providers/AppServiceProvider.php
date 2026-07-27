<?php

namespace App\Providers;

use RuntimeException;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->enforceStrictModels();
        $this->enforcePasswordPolicy();

        // Behind a load balancer the app otherwise generates http:// links and
        // can redirect a signed-in user out of TLS.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
            $this->requireWebhookSignatureVerification();
        }
    }

    /**
     * Refuse to boot in production without a Paddle webhook secret.
     *
     * Cashier only attaches its signature-verification middleware when
     * cashier.webhook_secret is set — see WebhookController::__construct. With
     * the secret missing the endpoint is unauthenticated, so anyone who knows
     * the URL can POST a subscription.created event and grant themselves a paid
     * account, or a subscription.canceled and revoke someone else's.
     *
     * That is a configuration mistake with no visible symptom: everything works,
     * quietly, until it is exploited. Better to fail at boot, where it is
     * obvious, than to serve a billing endpoint that trusts its callers.
     */
    private function requireWebhookSignatureVerification(): void
    {
        if (filled(config('cashier.webhook_secret'))) {
            return;
        }

        // Nothing to protect if billing is not configured at all — an install
        // running without Paddle is a legitimate deployment.
        if (blank(config('billing.price_id'))) {
            return;
        }

        throw new RuntimeException(
            'PADDLE_WEBHOOK_SECRET is not set. Without it the Paddle webhook accepts '
            .'unsigned requests, so anyone could grant themselves a subscription.'
        );
    }

    /**
     * Turn Eloquent's quiet failure modes into loud ones outside production.
     *
     * Bugs of exactly these kinds have already shipped here: a mistyped column
     * silently discarded on save, and an N+1 that only ever showed up as a slow
     * page. Strict mode makes each an exception during development instead of a
     * mystery in production — but stays off in production itself, where throwing
     * at a paying user is worse than the degraded behaviour.
     */
    private function enforceStrictModels(): void
    {
        // Deliberately NOT shouldBeStrict(), which also turns on
        // preventAccessingMissingAttributes. That one throws whenever a nullable
        // column was never written — reading $user->hevy_api_key on an account
        // that has not set one — which is normal here and would make the guard
        // something you switch off rather than something you keep.
        Model::preventLazyLoading(! $this->app->isProduction());

        // Never let a mistyped attribute vanish without trace, in any
        // environment: that is how a bad column reaches the database silently.
        Model::preventSilentlyDiscardingAttributes();
    }

    /**
     * "password" was an accepted password. For an app holding health data and,
     * shortly, payment details, that is not defensible.
     *
     * uncompromised() checks the k-anonymity Have I Been Pwned range API, so it
     * is production-only: local and CI runs must not depend on a network call.
     */
    private function enforcePasswordPolicy(): void
    {
        Password::defaults(function () {
            $rule = Password::min(10)->letters()->numbers();

            return $this->app->isProduction()
                ? $rule->uncompromised()
                : $rule;
        });
    }
}
