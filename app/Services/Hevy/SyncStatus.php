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
            'running' => ['state' => 'running', 'message' => __('app.sync.running'), 'log' => $latest],
            'failed' => [
                'state' => 'failed',
                'message' => __('app.sync.failed', ['error' => $latest->error ?: 'unknown error']),
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
                'message' => __('app.sync.stalled'),
                'log' => $log,
            ];
        }

        return [
            'state' => 'queued',
            'message' => __('app.sync.queued'),
            'log' => $log,
        ];
    }
}
