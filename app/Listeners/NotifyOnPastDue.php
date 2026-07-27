<?php

namespace App\Listeners;

use App\Mail\PaymentFailed;
use Illuminate\Support\Facades\Mail;
use Laravel\Paddle\Events\SubscriptionUpdated;

/**
 * Watches Paddle's webhook stream for a subscription going past due.
 *
 * One email per failure episode, not one per webhook: Paddle retries the card
 * on its own schedule and fires subscription.updated on every attempt, so the
 * watermark is only cleared once the subscription leaves the past-due state.
 */
class NotifyOnPastDue
{
    public function handle(SubscriptionUpdated $event): void
    {
        $subscription = $event->subscription;
        $user = $subscription->billable;

        if (! $user) {
            return;
        }

        if ($subscription->pastDue()) {
            if ($user->past_due_notified_at === null) {
                Mail::to($user)->locale($user->locale ?? config('app.locale'))
                    ->send(new PaymentFailed($user));

                $user->forceFill(['past_due_notified_at' => now()])->save();
            }

            return;
        }

        // Recovered (or cancelled): the next failure is a new episode and
        // deserves its own email.
        if ($user->past_due_notified_at !== null) {
            $user->forceFill(['past_due_notified_at' => null])->save();
        }
    }
}
