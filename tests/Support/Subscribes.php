<?php

namespace Tests\Support;

use App\Models\User;
use Laravel\Paddle\Subscription;

/**
 * Puts a user into a billing state without touching Paddle.
 *
 * Cashier reads subscription state from the local tables, so a test can write
 * the row Paddle's webhook would have written and exercise the real code path.
 * Faking the entitlement layer instead would test the fake.
 */
trait Subscribes
{
    protected function subscribe(User $user, string $status = Subscription::STATUS_ACTIVE, ?string $endsAt = null): Subscription
    {
        $customer = $user->customer()->create([
            'paddle_id' => 'ctm_'.fake()->unique()->numerify('##########'),
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $subscription = $user->subscriptions()->create([
            'type' => 'default',
            'paddle_id' => 'sub_'.fake()->unique()->numerify('##########'),
            'status' => $status,
            'ends_at' => $endsAt,
        ]);

        $subscription->items()->create([
            'product_id' => 'pro_test',
            'price_id' => config('billing.price_id') ?: 'pri_test',
            'status' => $status,
            'quantity' => 1,
        ]);

        $user->unsetRelation('subscriptions')->unsetRelation('customer');
        $customer->refresh();

        return $subscription->refresh();
    }

    /** Cancelled but still inside the period already paid for. */
    protected function subscribeOnGracePeriod(User $user): Subscription
    {
        return $this->subscribe($user, Subscription::STATUS_ACTIVE, now()->addDays(10)->toDateTimeString());
    }

    protected function subscribePastDue(User $user): Subscription
    {
        return $this->subscribe($user, Subscription::STATUS_PAST_DUE);
    }
}
