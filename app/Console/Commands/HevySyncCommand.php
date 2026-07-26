<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Hevy\HevySync;
use Illuminate\Console\Command;

class HevySyncCommand extends Command
{
    protected $signature = 'hevy:sync {user? : User id or email} {--force : Force a full re-sync}';

    protected $description = 'Sync Hevy data (workouts, routines, templates, body measurements) into the local database';

    public function handle(): int
    {
        $users = $this->resolveUsers();

        if ($users->isEmpty()) {
            $this->error('No users with a Hevy API key found.');

            return self::FAILURE;
        }

        foreach ($users as $user) {
            $this->info("Syncing Hevy data for {$user->email} ...");
            try {
                $counts = (new HevySync($user))->run((bool) $this->option('force'));
                foreach ($counts as $key => $value) {
                    $this->line(sprintf('  %-18s %d', $key, $value));
                }
                $this->info('  Done.');
            } catch (\Throwable $e) {
                $this->error("  Failed: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    private function resolveUsers()
    {
        $arg = $this->argument('user');

        if ($arg) {
            return User::query()
                ->where('id', $arg)
                ->orWhere('email', $arg)
                ->whereNotNull('hevy_api_key')
                ->get();
        }

        return User::query()->whereNotNull('hevy_api_key')->get();
    }
}
