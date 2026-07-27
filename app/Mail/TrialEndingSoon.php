<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your trial ends in N days."
 *
 * The single highest-leverage email a subscription product sends. Without it
 * the trial lapses in total silence: the athlete opens the app one day, their
 * history is capped at 30 days, and the product looks like it broke — at the
 * exact moment they were deciding whether to pay for it.
 */
class TrialEndingSoon extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        $days = max(1, (int) now()->diffInDays($this->user->trial_ends_at, false) + 1);

        // trans_choice, not __(): "ends in 1 days" in a subject line is the
        // kind of sloppiness people judge a product's care by.
        return new Envelope(
            subject: trans_choice('app.mail.trial_ending.subject', $days, ['days' => $days]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.trial-ending', with: [
            'user' => $this->user,
            'endsAt' => $this->user->trial_ends_at,
            'price' => config('billing.display_price'),
            'freeDays' => config('billing.entitlements.free.history_days'),
        ]);
    }
}
