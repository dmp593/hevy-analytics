<?php

namespace App\Console\Commands;

use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Give an account free access from the shell.
 *
 * The admin UI can already do this, but it needs an admin account to exist and
 * a browser to be pointed at the site. The first account — the operator's own,
 * on a fresh install, at the point where nothing is configured yet — has
 * neither. So this exists for the same reason app:make-admin does: the first
 * one has to come from somewhere, and shell access is the least dangerous
 * somewhere.
 *
 * With no --days, the grant has no end date. That is the intended shape for the
 * operator's own account: an access that quietly lapses is a bad surprise, and
 * a dated grant is a surprise with a timer on it.
 */
class GrantAccessCommand extends Command
{
    protected $signature = 'app:grant-access
        {email : The account to grant access to}
        {--days= : Expire after this many days (default: never)}
        {--reason= : Why, for the audit log}
        {--revoke : Take the access away instead}';

    protected $description = 'Grant or revoke complimentary access for an account';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No account with email {$email}.");

            return self::FAILURE;
        }

        return $this->option('revoke') ? $this->revoke($user) : $this->grant($user);
    }

    private function grant(User $user): int
    {
        // `--days=` with nothing after it arrives as an empty string, and the
        // person who typed it meant the same thing as leaving it out. Compared
        // against '' rather than falsily, because "0" is falsy in PHP and must
        // reach the check below to be refused rather than becoming "forever".
        $days = trim((string) ($this->option('days') ?? ''));
        $days = $days === '' ? null : $days;

        if ($days !== null && (! ctype_digit($days) || (int) $days < 1)) {
            $this->error('--days must be a whole number of days, at least 1. Leave it out for no expiry.');

            return self::FAILURE;
        }

        $until = $days === null ? null : now()->addDays((int) $days);
        $reason = trim((string) ($this->option('reason') ?? '')) ?: 'Granted from the console';

        $user->forceFill([
            'comped_until' => $until,
            'comped_reason' => $reason,
        ])->save();

        // admin: null — this ran from a shell, not from an admin's session. The
        // log says so rather than crediting whoever happens to be an admin.
        AdminAction::record(
            null,
            $user,
            AdminAction::GRANTED_ACCESS,
            $until === null
                ? __('app.admin.granted_detail_forever', ['reason' => $reason])
                : __('app.admin.granted_detail', ['days' => (int) $days, 'until' => $until->toDateString(), 'reason' => $reason]),
        );

        $this->info($until === null
            ? "{$user->email} now has complimentary access with no end date."
            : "{$user->email} now has complimentary access until {$until->toDateString()}.");

        $this->line('  Reason recorded: '.$reason);

        return self::SUCCESS;
    }

    private function revoke(User $user): int
    {
        if ($user->comped_until === null && $user->comped_reason === null) {
            $this->warn("{$user->email} has no complimentary access to revoke.");

            return self::SUCCESS;
        }

        $user->forceFill(['comped_until' => null, 'comped_reason' => null])->save();

        AdminAction::record(null, $user, AdminAction::REVOKED_ACCESS);

        $this->info("Complimentary access removed from {$user->email}.");
        $this->line('  They are now '.$user->fresh()->billingState()->label().'.');

        return self::SUCCESS;
    }
}
