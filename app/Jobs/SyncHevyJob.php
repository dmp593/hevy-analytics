<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Hevy\HevySync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncHevyJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(
        public int $userId,
        public bool $force = false,
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user || ! $user->hasHevyKey()) {
            return;
        }

        (new HevySync($user))->run($this->force);
    }
}
