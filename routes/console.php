<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep every account's Hevy data fresh with incremental syncs. --queue fans out
// one job per user so a single slow account cannot stall everyone else's sync.
Schedule::command('hevy:sync --queue')->hourly()->withoutOverlapping();
