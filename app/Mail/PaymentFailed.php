<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Your payment is failing."
 *
 * A past-due subscription is almost always an expired card, not a decision to
 * leave — but Paddle only retries for so long before cancelling. The app keeps
 * serving the athlete through the past-due window (that is deliberate, see
 * State::configKey), which also means nothing in the UI forces them to notice.
 * This email is the notice.
 */
class PaymentFailed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('app.mail.payment_failed.subject'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.payment-failed', with: [
            'user' => $this->user,
        ]);
    }
}
