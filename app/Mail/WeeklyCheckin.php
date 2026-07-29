<?php

namespace App\Mail;

use App\Models\User;
use App\Services\Analytics\BodyCompAnalytics;
use App\Services\Analytics\ConsistencyAnalytics;
use App\Services\Analytics\GoalAlerts;
use App\Support\Units;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The Monday check-in: the dashboard's top guidance, in the inbox.
 *
 * Assembled from the same services the dashboard reads, so the email can
 * never say something the app would not — same trend weight, same alerts,
 * same consistency numbers. Nothing is computed specially for the email,
 * because a second source of truth is how the two drift apart.
 */
class WeeklyCheckin extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('app.mail.weekly.subject', ['app' => config('app.name')]),
        );
    }

    public function content(): Content
    {
        $bc = new BodyCompAnalytics($this->user);
        $status = $bc->status();
        $units = Units::for($this->user);

        return new Content(markdown: 'mail.weekly-checkin', with: [
            'user' => $this->user,
            'units' => $units,
            'trendWeight' => $status['trend_weight_kg'] ?? $status['weight_kg'] ?? null,
            'rate' => $bc->weightRateKgPerWeek(),
            // The dashboard leads with two alerts; the email mirrors it.
            'alerts' => array_slice((new GoalAlerts($this->user))->all(), 0, 2),
            'consistency' => (new ConsistencyAnalytics($this->user))->summary(),
        ]);
    }
}
