<?php

namespace Tests\Feature;

use App\Listeners\NotifyOnPastDue;
use App\Mail\PaymentFailed;
use App\Mail\TrialEndingSoon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Paddle\Events\SubscriptionUpdated;
use Tests\Support\Subscribes;
use Tests\TestCase;

/**
 * The two emails that guard revenue: "your trial is ending" and "your card is
 * failing". The property that matters most is exactly-once — a duplicate
 * warning email reads as a broken product, and a missing one costs the sale.
 */
class NotificationEmailsTest extends TestCase
{
    use RefreshDatabase, Subscribes;

    // ------------------------------------------------------------- trial

    public function test_an_ending_trial_gets_exactly_one_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['trial_ends_at' => now()->addDays(2)]);

        $this->artisan('app:send-trial-emails')->assertSuccessful();
        $this->artisan('app:send-trial-emails')->assertSuccessful();

        Mail::assertSent(TrialEndingSoon::class, 1);
        Mail::assertSent(TrialEndingSoon::class, fn ($mail) => $mail->hasTo($user->email));
        $this->assertNotNull($user->fresh()->trial_ending_notified_at);
    }

    public function test_a_trial_with_days_to_go_is_not_warned_yet(): void
    {
        Mail::fake();

        User::factory()->create(['trial_ends_at' => now()->addDays(10)]);

        $this->artisan('app:send-trial-emails');

        Mail::assertNothingSent();
    }

    public function test_an_already_expired_trial_is_not_warned(): void
    {
        Mail::fake();

        User::factory()->create(['trial_ends_at' => now()->subDay()]);

        $this->artisan('app:send-trial-emails');

        Mail::assertNothingSent();
    }

    /** Selling a subscription to someone who already has one reads as a bug. */
    public function test_a_subscriber_whose_trial_dates_linger_is_not_emailed(): void
    {
        Mail::fake();

        $user = User::factory()->create(['trial_ends_at' => now()->addDays(2)]);
        $this->subscribe($user);

        $this->artisan('app:send-trial-emails');

        Mail::assertNothingSent();
    }

    public function test_a_comped_account_is_not_emailed(): void
    {
        Mail::fake();

        User::factory()->create([
            'trial_ends_at' => now()->addDays(2),
            'comped_reason' => 'Owner',
            'comped_until' => null,
        ]);

        $this->artisan('app:send-trial-emails');

        Mail::assertNothingSent();
    }

    public function test_the_email_is_sent_in_the_users_language(): void
    {
        Mail::fake();

        User::factory()->create(['trial_ends_at' => now()->addDays(2), 'locale' => 'pt']);

        $this->artisan('app:send-trial-emails');

        Mail::assertSent(TrialEndingSoon::class, fn ($mail) => $mail->locale === 'pt');
    }

    public function test_dry_run_sends_nothing_and_stamps_nothing(): void
    {
        Mail::fake();

        $user = User::factory()->create(['trial_ends_at' => now()->addDays(2)]);

        $this->artisan('app:send-trial-emails --dry-run')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($user->fresh()->trial_ending_notified_at);
    }

    // --------------------------------------------------------- past due

    public function test_going_past_due_sends_one_email_per_episode(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $subscription = $this->subscribePastDue($user);

        $listener = new NotifyOnPastDue;

        // Paddle retries the card and fires subscription.updated every time.
        $listener->handle(new SubscriptionUpdated($subscription, []));
        $listener->handle(new SubscriptionUpdated($subscription->fresh(), []));

        Mail::assertSent(PaymentFailed::class, 1);
    }

    public function test_recovery_arms_the_next_episode(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $subscription = $this->subscribePastDue($user);
        $listener = new NotifyOnPastDue;

        $listener->handle(new SubscriptionUpdated($subscription, []));

        // The card gets fixed…
        $subscription->update(['status' => 'active']);
        $listener->handle(new SubscriptionUpdated($subscription->fresh(), []));
        $this->assertNull($user->fresh()->past_due_notified_at);

        // …and fails again months later: that deserves a fresh email.
        $subscription->update(['status' => 'past_due']);
        $listener->handle(new SubscriptionUpdated($subscription->fresh(), []));

        Mail::assertSent(PaymentFailed::class, 2);
    }

    /** The templates must actually render — a missing lang key or view throws here. */
    public function test_both_emails_render_in_both_languages(): void
    {
        $user = User::factory()->create(['trial_ends_at' => now()->addDays(2)]);

        foreach (['en', 'pt'] as $locale) {
            app()->setLocale($locale);

            $this->assertNotSame('', (new TrialEndingSoon($user))->render());
            $this->assertNotSame('', (new PaymentFailed($user))->render());
        }
    }
}
