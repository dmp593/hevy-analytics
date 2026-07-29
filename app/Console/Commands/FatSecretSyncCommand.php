<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FatSecret\FatSecretClient;
use App\Services\FatSecret\FatSecretSync;
use Illuminate\Console\Command;

class FatSecretSyncCommand extends Command
{
    protected $signature = 'fatsecret:sync {--days=7 : How many recent days to re-read}';

    protected $description = 'Pull diary totals for every account with a linked FatSecret profile';

    public function handle(): int
    {
        if (! FatSecretClient::configured()) {
            $this->warn('FatSecret credentials are not configured; nothing to do.');

            return self::SUCCESS;
        }

        $sync = new FatSecretSync;

        User::query()
            ->whereNotNull('fatsecret_linked_at')
            ->where('is_demo', false)
            ->each(function (User $user) use ($sync) {
                try {
                    $days = $sync->run($user, (int) $this->option('days'));
                    $this->info("{$user->email}: {$days} day(s)");
                } catch (\Throwable $e) {
                    // One broken link must not stop the fleet; the person can
                    // relink from their profile.
                    $this->error("{$user->email}: {$e->getMessage()}");
                }
            });

        return self::SUCCESS;
    }
}
