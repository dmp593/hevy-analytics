<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Hevy\HevySync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * ShouldBeUnique deduplicates at DISPATCH time, keyed by user.
 *
 * Checking sync_logs in the controller could not work: the log row is created
 * inside this job, so at dispatch there is nothing yet to see and five rapid
 * clicks queued five full syncs against the same account.
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
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user || ! $user->hasHevyKey()) {
            return;
        }

        (new HevySync($user))->run($this->force);
    }
}
