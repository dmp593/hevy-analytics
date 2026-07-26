<?php

namespace App\Console\Commands;

use App\Jobs\SyncHevyJob;
use App\Models\User;
use App\Services\Hevy\HevySync;
use Illuminate\Console\Command;

class HevySyncCommand extends Command
{
    protected $signature = 'hevy:sync
        {user? : User id or email}
        {--force : Force a full re-sync}
        {--queue : Dispatch one queued job per user instead of syncing inline}';

    protected $description = 'Sync Hevy data (workouts, routines, templates, body measurements) into the local database';

    public function handle(): int
    {
        $users = $this->resolveUsers();

        if ($users->isEmpty()) {
            $this->error('No users with a Hevy API key found.');

            return self::FAILURE;
        }

        // Scheduled runs dispatch rather than sync inline: walking every account
        // sequentially in one process means one slow or failing account delays
        // everybody behind it, and the whole run dies together.
        if ($this->option('queue')) {
            foreach ($users as $user) {
                SyncHevyJob::dispatch($user->id, (bool) $this->option('force'));
            }

            $this->info("Queued {$users->count()} sync job(s).");

            return self::SUCCESS;
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
            // The id/email alternation must be grouped: AND binds tighter than
            // OR, so an ungrouped chain matched users by id regardless of
            // whether they had an API key, handing null to HevyClient.
            return User::query()
                ->where(fn ($q) => $q->where('id', $arg)->orWhere('email', $arg))
                ->whereNotNull('hevy_api_key')
                ->get();
        }

        return User::query()->whereNotNull('hevy_api_key')->get();
    }
}
