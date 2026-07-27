<?php

namespace App\Jobs;

use App\Models\SyncLog;
use App\Models\User;
use App\Services\Hevy\HevySync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * ShouldBeUnique deduplicates at DISPATCH time, keyed by user.
 *
 * Checking sync_logs in the controller could not work on its own: five rapid
 * clicks all looked like the first one because nothing had started yet.
 */
class SyncHevyJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    /** Release the lock after this long so a crashed worker cannot wedge a user. */
    public int $uniqueFor = 900;

    public function __construct(
        public int $userId,
        public bool $force = false,
        public ?int $syncLogId = null,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        $log = $this->syncLogId ? SyncLog::find($this->syncLogId) : null;

        if (! $user || ! $user->hasHevyKey()) {
            $log?->update([
                'status' => 'failed',
                'error' => 'No Hevy API key is set on this account.',
                'finished_at' => now(),
            ]);

            return;
        }

        (new HevySync($user))->run($this->force, $log);
    }

    /**
     * A job that exhausts its retries or times out must not leave the UI showing
     * "queued" forever — that is the failure mode moving sync onto the queue
     * introduced.
     */
    public function failed(?\Throwable $e): void
    {
        if ($this->syncLogId) {
            SyncLog::where('id', $this->syncLogId)
                ->whereIn('status', ['queued', 'running'])
                ->update([
                    'status' => 'failed',
                    'error' => $e?->getMessage() ?? 'The sync job did not complete.',
                    'finished_at' => now(),
                ]);
        }
    }
}
