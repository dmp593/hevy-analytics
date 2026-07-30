<?php

namespace Tests\Feature;

use App\Services\Analytics\RollupBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

/** The derived daily aggregates: rebuilt from raw sets, warmups excluded. */
class RollupBuilderTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    public function test_rebuild_aggregates_per_day_and_exercise(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);
        $day = Carbon::parse('2026-07-20 18:00:00');
        $this->seedWorkout($user, $day, ['sq'], 3, 100.0, 5);
        $this->seedWorkout($user, $day->copy()->addDays(2), ['sq'], 2, 110.0, 3);

        (new RollupBuilder)->rebuild($user);

        $rows = DB::table('workout_set_rollups')->where('user_id', $user->id)
            ->orderBy('local_date')->get();

        $this->assertCount(2, $rows);
        $this->assertSame('2026-07-20', (string) $rows[0]->local_date);
        $this->assertSame(3, (int) $rows[0]->sets);
        $this->assertSame(15, (int) $rows[0]->reps);
        $this->assertEqualsWithDelta(1500.0, (float) $rows[0]->tonnage, 0.01);
        $this->assertSame('quadriceps', $rows[0]->primary_muscle_group);
        $this->assertEqualsWithDelta(110.0, (float) $rows[1]->best_weight, 0.01);
    }

    public function test_rebuild_is_idempotent_and_replaces_stale_rows(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);
        $this->seedWorkout($user, Carbon::parse('2026-07-20 18:00:00'), ['sq'], 3);

        (new RollupBuilder)->rebuild($user);
        (new RollupBuilder)->rebuild($user);

        $this->assertSame(1, DB::table('workout_set_rollups')->where('user_id', $user->id)->count());
    }
}
