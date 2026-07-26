<?php

namespace App\Services\Hevy;

use App\Models\SyncLog;
use App\Models\User;

/**
 * What is happening with this user's sync, in terms the UI can show.
 *
 * Moving sync onto the queue removed the one thing the blocking version had:
 * the user saw the result. Queued work fails invisibly — no worker running, a
 * dead worker, an expired Hevy key — and without this the app would cheerfully
 * say "Sync started" forever while nothing ever happened.
 *
 * State comes from sync_logs rather than from the queue tables, so it works
 * whichever queue driver is configured.
 */
class SyncStatus
{
    /** A sync still 'queued' after this long means nothing is consuming the queue. */
    private const STALLED_AFTER_MINUTES = 2;

    public function __construct(private readonly User $user) {}

    /**
     * @return array{state: string, message: ?string, log: ?SyncLog}
     *                                                               state is one of: idle, queued, stalled, running, failed, synced
     */
    public function current(): array
    {
        $latest = $this->user->syncLogs()->latest('id')->first();

        if ($latest === null) {
            return ['state' => 'idle', 'message' => null, 'log' => null];
        }

        return match ($latest->status) {
            'queued' => $this->queuedState($latest),
            'running' => ['state' => 'running', 'message' => 'Syncing your Hevy data…', 'log' => $latest],
            'failed' => [
                'state' => 'failed',
                'message' => 'Your last sync failed: '.($latest->error ?: 'unknown error'),
                'log' => $latest,
            ],
            default => ['state' => 'synced', 'message' => null, 'log' => $latest],
        };
    }

    public function isPending(): bool
    {
        return in_array($this->current()['state'], ['queued', 'stalled', 'running'], true);
    }

    private function queuedState(SyncLog $log): array
    {
        $stalled = $log->created_at !== null
            && $log->created_at->lt(now()->subMinutes(self::STALLED_AFTER_MINUTES));

        if ($stalled) {
            return [
                'state' => 'stalled',
                'message' => 'Your sync is queued but nothing has picked it up. If you are running this app '
                    .'yourself, start a queue worker: php artisan queue:work',
                'log' => $log,
            ];
        }

        return [
            'state' => 'queued',
            'message' => 'Sync queued — refresh in a moment to see your new data.',
            'log' => $log,
        ];
    }
}
