<?php

namespace App\Console\Commands;

use App\Mail\WeeklyCheckin;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Runs weekly from the scheduler (Monday mornings).
 *
 * Idempotent the same way the trial email is: the watermark is stamped in
 * the same pass as the send, so a double-firing scheduler cannot email
 * anyone twice. Eligibility is deliberately narrow — an account with no
 * workouts has no week to summarise, and an email with nothing to say
 * teaches people to delete the sender.
 */
class SendWeeklyCheckinsCommand extends Command
{
    protected $signature = 'app:send-weekly-checkins {--dry-run : List who would be emailed without sending}';

    protected $description = 'Send the weekly check-in email to opted-in accounts with data';

    public function handle(): int
    {
        $users = User::query()
            ->where('is_demo', false)
            ->where('weekly_email', true)
            ->whereHas('workouts')
            ->where(function ($q) {
                $q->whereNull('weekly_checkin_sent_at')
                    ->orWhere('weekly_checkin_sent_at', '<=', now()->subDays(6));
            })
            ->get();

        foreach ($users as $user) {
            if ($this->option('dry-run')) {
                $this->line("  would email {$user->email}");

                continue;
            }

            Mail::to($user)->locale($user->locale ?? config('app.locale'))
                ->send(new WeeklyCheckin($user));

            $user->forceFill(['weekly_checkin_sent_at' => now()])->save();
        }

        $this->info(sprintf('%d account(s) %s.', $users->count(), $this->option('dry-run') ? 'matched' : 'emailed'));

        return self::SUCCESS;
    }
}
