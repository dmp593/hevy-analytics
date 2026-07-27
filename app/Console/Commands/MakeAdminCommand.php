<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Promote an account to admin.
 *
 * Deliberately a console command and not a UI: there is no "make me an admin"
 * button anywhere in the app, so the only way to get the flag is shell access to
 * the server. The first admin has to come from somewhere, and this is the least
 * dangerous somewhere.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'app:make-admin {email} {--revoke : Remove admin rights instead}';

    protected $description = 'Grant or revoke admin rights for an account';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No account with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        $revoking = (bool) $this->option('revoke');

        if ($revoking && User::where('is_admin', true)->count() === 1 && $user->is_admin) {
            $this->error('That is the only admin account. Promote another before revoking this one.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $revoking])->save();

        $this->info($revoking
            ? "{$user->email} is no longer an admin."
            : "{$user->email} is now an admin.");

        return self::SUCCESS;
    }
}
