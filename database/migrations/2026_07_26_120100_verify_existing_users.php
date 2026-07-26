<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grandfather accounts that predate email verification being enforced.
 *
 * The User model did not implement MustVerifyEmail, so the `verified` middleware
 * was a no-op and nobody was ever asked to confirm their address. Turning it on
 * would retroactively lock out every existing account -- including the operator's
 * own -- for failing a check that did not exist when they signed up.
 *
 * New signups go through verification normally; this only backfills the ones
 * that came before.
 */
return new class extends Migration
{
    /** Accounts created after this predate nothing and must verify normally. */
    private const CUTOFF = '2026-07-26 12:01:00';

    public function up(): void
    {
        // Bounded to accounts that already existed when this ran. Without the
        // cutoff, a rollback and re-migrate would silently verify every account
        // created since -- including ones that never confirmed their address.
        DB::table('users')
            ->whereNull('email_verified_at')
            ->where('created_at', '<=', self::CUTOFF)
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Irreversible by design: we cannot tell which of these addresses were
        // genuinely verified, and un-verifying them would lock people out.
    }
};
