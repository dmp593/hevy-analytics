<?php

namespace App\Listeners;

use App\Jobs\SyncHevyJob;
use App\Services\Hevy\SyncStatus;
use Illuminate\Auth\Events\Login;

/**
 * Keep the data fresh without making anyone think about it.
 *
 * The complaint this prevents: open the app in the morning, yesterday's workout
 * is missing, press sync, wait. But login is not a signal that new data exists,
 * so syncing on EVERY login would hammer Hevy's API (workout pages are capped
 * at 10 items) for people who sign in five times a day. The staleness window is
 * the compromise: at most one background sync per account per window, and only
 * an incremental one — a handful of requests via /v1/workouts/events.
 */
class QueueSyncOnLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        // The demo account has a fake key; syncing it would only log failures.
        if (! $user->hasHevyKey() || $user->is_demo) {
            return;
        }

        $hours = (int) config('services.hevy.auto_sync_hours', 6);

        if ($hours <= 0) {
            return;
        }

        if ($user->hevy_last_synced_at?->gt(now()->subHours($hours))) {
            return;
        }

        if ((new SyncStatus($user))->isPending()) {
            return;
        }

        // Same shape as the manual sync button: a queued log row so the UI can
        // show something is happening, then the deduplicated job.
        $log = $user->syncLogs()->create(['type' => 'incremental', 'status' => 'queued']);

        SyncHevyJob::dispatch($user->id, false, $log->id);
    }
}
