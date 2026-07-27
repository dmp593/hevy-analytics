<?php

namespace App\Billing;

use App\Models\User;

/**
 * Which billing state an athlete is in.
 *
 * This is the ONLY place that asks Cashier anything. Everything downstream works
 * from the enum, so replacing the provider means rewriting one method rather
 * than auditing every call site for a Paddle-shaped assumption.
 */
enum State: string
{
    case Trialing = 'trial';
    case Subscribed = 'subscribed';

    /** Was subscribed, cancelled, still inside the paid period. */
    case GracePeriod = 'grace';

    /** Access granted by an admin: support, a refund, a friend of the house. */
    case Complimentary = 'comped';

    /** Paddle says the payment is failing. Still served, but told about it. */
    case PastDue = 'past_due';

    case Free = 'free';

    public static function for(User $user): self
    {
        $subscription = $user->subscription();

        if ($subscription) {
            // Order matters. A past-due subscription is also "active" as far as
            // Cashier is concerned, and a cancelled one inside its grace period
            // is too — checking active() first would collapse three states that
            // need different messages into one.
            if ($subscription->pastDue()) {
                return self::PastDue;
            }

            if ($subscription->onGracePeriod()) {
                return self::GracePeriod;
            }

            if ($subscription->valid()) {
                return self::Subscribed;
            }
        }

        // Before the trial, after the subscription: a comp is what an admin
        // deliberately did, so it should not be hidden behind a trial that
        // happens to still be running, and should not override money the
        // athlete is actually paying.
        //
        // comped_reason is what marks a comp as existing; comped_until is an
        // OPTIONAL expiry. A null expiry means indefinite, which is what the
        // operator's own account needs — an access grant that quietly lapses
        // and locks you out of your own product is a bad surprise, and every
        // dated grant would eventually become one.
        if ($user->isComped()) {
            return self::Complimentary;
        }

        // Checked after the subscription, so someone who subscribes during
        // their trial is reported as subscribed rather than as still trialing.
        //
        // Read from users.trial_ends_at rather than Cashier's onGenericTrial(),
        // which requires a Paddle customer to exist. The trial is card-free, so
        // at this point Paddle has never heard of them.
        if ($user->trial_ends_at?->isFuture()) {
            return self::Trialing;
        }

        return self::Free;
    }

    /**
     * Which entitlement block applies.
     *
     * Grace period and past-due both map to the paid entitlements: they have
     * paid for the period they are in, and cutting someone off mid-period
     * because a card expired is how you turn a billing hiccup into a
     * cancellation.
     */
    public function configKey(): string
    {
        return match ($this) {
            self::Subscribed, self::GracePeriod, self::PastDue, self::Complimentary => 'subscribed',
            self::Trialing => 'trial',
            self::Free => 'free',
        };
    }

    /** Whether the athlete currently has full access, however they got it. */
    public function hasFullAccess(): bool
    {
        return $this !== self::Free;
    }

    /** Whether to show a "start subscribing" call to action. */
    public function shouldPromptToSubscribe(): bool
    {
        // Not shown to a comped account: inviting someone to pay for access an
        // admin just gave them for free reads as a mistake.
        return in_array($this, [self::Free, self::Trialing, self::GracePeriod], true);
    }

    public function label(): string
    {
        return __('app.billing.state.'.$this->value);
    }
}
