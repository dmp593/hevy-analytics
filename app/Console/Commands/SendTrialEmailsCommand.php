<?php

namespace App\Console\Commands;

use App\Billing\State;
use App\Mail\TrialEndingSoon;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Runs daily from the scheduler.
 *
 * Idempotent by design: the watermark column is stamped in the same pass as
 * the send, so running the command twice — or the scheduler double-firing
 * across a deploy — cannot email anyone twice. That guarantee lives in the
 * data, not in scheduling discipline.
 */
class SendTrialEmailsCommand extends Command
{
    /** Days of warning before the trial ends. */
    public const DAYS_BEFORE = 3;

    protected $signature = 'app:send-trial-emails {--dry-run : List who would be emailed without sending}';

    protected $description = 'Email accounts whose trial is about to end';

    public function handle(): int
    {
        $users = User::query()
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(self::DAYS_BEFORE)])
            ->whereNull('trial_ending_notified_at')
            ->where('is_demo', false)
            ->get()
            // Someone who already subscribed, or was comped, does not need to be
            // sold what they already have. Resolved through billingState() so
            // this agrees with the app rather than re-deriving the rules.
            ->filter(fn (User $u) => in_array($u->billingState(), [
                State::Trialing,
            ], true));

        foreach ($users as $user) {
            if ($this->option('dry-run')) {
                $this->line("  would email {$user->email} (trial ends {$user->trial_ends_at->toDateString()})");

                continue;
            }

            Mail::to($user)->locale($user->locale ?? config('app.locale'))
                ->send(new TrialEndingSoon($user));

            $user->forceFill(['trial_ending_notified_at' => now()])->save();
        }

        $this->info(sprintf('%d account(s) %s.', $users->count(), $this->option('dry-run') ? 'matched' : 'emailed'));

        return self::SUCCESS;
    }
}
