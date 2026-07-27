<?php

namespace App\Http\Controllers;

use App\Billing\State;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $state = $user->billingState();
        $subscription = $user->subscription();

        return view('billing.show', [
            'state' => $state,
            'subscription' => $subscription,
            'entitlements' => $user->entitlements(),
            'trialEndsAt' => $user->trial_ends_at,
            'price' => config('billing.display_price'),
            'priceId' => config('billing.price_id'),
            // Paddle's overlay checkout needs a transaction created server-side.
            // Only built when the athlete could actually act on it, because
            // creating one calls Paddle and creates a customer record.
            'checkout' => $this->checkoutFor($request, $state),
            // Payment history lives at Paddle, so reading it is a network call
            // that can fail. Someone checking when their subscription renews
            // must not meet a 500 because a third party is having an outage —
            // the page simply omits the section it could not load.
            'lastPayment' => $this->fromPaddle(fn () => $subscription?->lastPayment()),
            'nextPayment' => $this->fromPaddle(fn () => $subscription?->nextPayment()),
        ]);
    }

    public function cancel(Request $request)
    {
        $subscription = $request->user()->subscription();

        abort_unless($subscription && $subscription->valid(), 404);

        // Cancels at period end, not immediately. They have paid for the rest of
        // the period and taking it away would be keeping money for time not served.
        $subscription->cancel();

        return redirect()->route('billing')->with('status', __('app.billing.cancelled'));
    }

    public function resume(Request $request)
    {
        $subscription = $request->user()->subscription();

        abort_unless($subscription && $subscription->onGracePeriod(), 404);

        $subscription->stopCancelation();

        return redirect()->route('billing')->with('status', __('app.billing.resumed'));
    }

    /**
     * Run a call that reaches Paddle, degrading to null rather than to a 500.
     *
     * Reported, not swallowed silently: an outage should be visible in the logs
     * even though it is invisible on the page.
     */
    private function fromPaddle(callable $call): mixed
    {
        try {
            return $call();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Build a Paddle checkout, or null when there is nothing to buy.
     *
     * Failure here must not take the page down: an athlete arriving to check
     * when their subscription renews should not see a 500 because Paddle is
     * having an outage. The view falls back to explaining the situation.
     */
    private function checkoutFor(Request $request, State $state): ?object
    {
        if (! $state->shouldPromptToSubscribe() || blank(config('billing.price_id'))) {
            return null;
        }

        try {
            return $request->user()
                ->checkout(config('billing.price_id'))
                ->returnTo(route('billing'));
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
