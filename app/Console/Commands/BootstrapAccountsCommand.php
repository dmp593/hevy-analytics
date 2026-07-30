<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the operator's accounts on a fresh deployment.
 *
 * Credentials come from the ENVIRONMENT, never from code or arguments:
 * a password in a seeder is a password in git history forever, and one in a
 * command argument lands in shell history and process listings. Environment
 * variables are set once in the host's dashboard and read here.
 *
 *   BOOTSTRAP_OWNER_EMAIL / BOOTSTRAP_OWNER_PASSWORD
 *       Full account, complimentary access with no end date.
 *   BOOTSTRAP_ADMIN_EMAIL / BOOTSTRAP_ADMIN_PASSWORD
 *       Same, plus the admin flag.
 *
 * Idempotent: an account that already exists is left exactly as it is — this
 * command must be safe to run on every deploy, and a deploy must never quietly
 * reset a password.
 */
class BootstrapAccountsCommand extends Command
{
    protected $signature = 'app:bootstrap-accounts';

    protected $description = 'Create the operator accounts defined in the environment';

    public function handle(): int
    {
        $made = 0;
        $made += $this->ensure(env('BOOTSTRAP_OWNER_EMAIL'), env('BOOTSTRAP_OWNER_PASSWORD'), admin: false);
        $made += $this->ensure(env('BOOTSTRAP_ADMIN_EMAIL'), env('BOOTSTRAP_ADMIN_PASSWORD'), admin: true);

        if ($made === 0) {
            $this->line('Nothing to do — accounts already exist or no BOOTSTRAP_* variables are set.');
        }

        return self::SUCCESS;
    }

    private function ensure(?string $email, ?string $password, bool $admin): int
    {
        if (blank($email)) {
            return 0;
        }

        if (blank($password)) {
            $this->warn("Skipping {$email}: its BOOTSTRAP_*_PASSWORD is not set.");

            return 0;
        }

        if ($existing = User::where('email', $email)->first()) {
            // The env var is authoritative for the ADMIN FLAG even on existing
            // accounts: an account created before the variables were set (or
            // recreated during an env outage) must still end up an admin, or
            // the operator is locked out of their own admin area with no UI
            // path back in. Promotion only — demotion stays an explicit
            // app:make-admin decision.
            $changed = [];

            if ($admin && ! $existing->is_admin) {
                $existing->forceFill(['is_admin' => true]);
                $changed[] = 'admin flag';
            }

            // The operator accounts' complimentary access is part of the same
            // contract: an account that predates the variables (or survived
            // an env outage) must still end up comped, or the owner pays for
            // their own product when the trial lapses.
            if ($existing->comped_reason === null) {
                $existing->forceFill([
                    'comped_reason' => 'Operator account (bootstrap)',
                    'comped_until' => null,
                ]);
                $changed[] = 'complimentary access';
            }

            if ($changed !== []) {
                $existing->save();
                $this->info("{$email} existed — granted: ".implode(', ', $changed).'.');
            } else {
                $this->line("{$email} already exists — left untouched.");
            }

            return 0;
        }

        User::forceCreate([
            'name' => ucfirst(strtok($email, '@')),
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_admin' => $admin,
            // The operator's own access must never quietly lapse.
            'comped_reason' => 'Operator account (bootstrap)',
            'comped_until' => null,
        ]);

        $this->info("Created {$email}".($admin ? ' (admin)' : '').' with indefinite complimentary access.');

        return 1;
    }
}
