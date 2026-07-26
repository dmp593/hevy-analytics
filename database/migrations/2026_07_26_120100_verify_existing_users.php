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
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Irreversible by design: we cannot tell which of these addresses were
        // genuinely verified, and un-verifying them would lock people out.
    }
};
