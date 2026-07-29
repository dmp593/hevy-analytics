<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep every account's Hevy data fresh with incremental syncs. --queue fans out
// one job per user so a single slow account cannot stall everyone else's sync.
Schedule::command('hevy:sync --queue')->hourly()->withoutOverlapping();

// Diet diaries change less than training logs, and people back-edit
// yesterday's meals — one nightly pass re-reading a week covers both.
Schedule::command('fatsecret:sync')->dailyAt('03:30')->withoutOverlapping();

// Trial-ending warnings. Idempotent (watermarked per user), so daily is a
// cadence choice, not a correctness requirement.
Schedule::command('app:send-trial-emails')->dailyAt('09:00');

// The Monday check-in: the dashboard's top guidance, in the inbox. Also
// watermarked per user, so the schedule is cadence, not correctness.
Schedule::command('app:send-weekly-checkins')->weeklyOn(1, '08:00');

// sync_logs grows ~24 rows per user per day and is read on every
// dashboard; two weeks of history is plenty for the status banner.
Schedule::call(fn () => DB::table('sync_logs')
    ->where('created_at', '<', now()->subDays(14))->delete())
    ->dailyAt('04:30')->name('prune-sync-logs');

// Reseed the public demo weekly so its dates never age into "last workout
// 5 months ago" — a stale demo quietly argues against the product.
Schedule::command('app:demo')->weeklyOn(1, '04:00');
