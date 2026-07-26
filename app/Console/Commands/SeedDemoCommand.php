<?php

namespace App\Console\Commands;

use App\Models\WorkoutSet;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/**
 * One command to a populated account.
 *
 * An empty app is impossible to develop against and impossible to demo: every
 * chart is blank, every metric reads "—", and the pages you most want to look at
 * are exactly the ones with nothing on them. Several of the bugs in this
 * codebase survived precisely because nobody could see them without real data.
 */
class SeedDemoCommand extends Command
{
    protected $signature = 'app:demo
        {--email=demo@example.test : Account to create or refresh}
        {--weeks=40 : How much training history to generate}
        {--fresh : Wipe the database first}';

    protected $description = 'Create a demo account with realistic training history';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->warn('Wiping the database…');
            $this->call('migrate:fresh', ['--force' => true]);
        }

        $email = (string) $this->option('email');
        $weeks = max(1, (int) $this->option('weeks'));

        $this->info("Seeding {$weeks} weeks of training for {$email}…");

        $user = (new DemoSeeder)->run($email, $weeks);

        $this->newLine();
        $this->line('  <fg=green>Ready.</> Sign in with:');
        $this->line("    email    <options=bold>{$email}</>");
        $this->line('    password <options=bold>password</>');
        $this->newLine();
        $this->table(
            ['workouts', 'sets', 'measurements'],
            [[
                $user->workouts()->count(),
                WorkoutSet::whereHas(
                    'workoutExercise.workout',
                    fn ($q) => $q->where('user_id', $user->id)
                )->count(),
                $user->bodyMeasurements()->count(),
            ]]
        );

        return self::SUCCESS;
    }
}
