<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\User;
use App\Services\Hevy\RoutineProgression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

class RoutineProgressionTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    private function routineWith(User $user, array $sets): Routine
    {
        $routine = $user->routines()->create(['hevy_id' => 'r1', 'title' => 'Push']);
        $routine->exercises()->create([
            'index' => 0,
            'title' => 'Bench Press (Barbell)',
            'exercise_template_hevy_id' => 'BENCH',
            'sets' => $sets,
        ]);

        return $routine;
    }

    public function test_adds_a_rep_below_the_rep_cap(): void
    {
        $user = User::factory()->create();
        $routine = $this->routineWith($user, [
            ['type' => 'normal', 'weight_kg' => 80, 'reps' => 8],
        ]);

        $result = (new RoutineProgression($user))->build($routine);
        $set = $result['payload']['routine']['exercises'][0]['sets'][0];

        $this->assertEquals(80.0, $set['weight_kg']);
        $this->assertSame(9, $set['reps']);
        $this->assertStringContainsString('80kg×8 → 80kg×9', $result['changes'][0]);
    }

    public function test_bumps_load_and_resets_reps_at_the_cap(): void
    {
        $user = User::factory()->create();
        $routine = $this->routineWith($user, [
            ['type' => 'normal', 'weight_kg' => 80, 'reps' => 12],
        ]);

        $set = (new RoutineProgression($user))->build($routine)['payload']['routine']['exercises'][0]['sets'][0];

        $this->assertEquals(82.5, $set['weight_kg']);
        $this->assertSame(8, $set['reps']);
    }

    public function test_warmups_are_untouched(): void
    {
        $user = User::factory()->create();
        $routine = $this->routineWith($user, [
            ['type' => 'warmup', 'weight_kg' => 40, 'reps' => 10],
        ]);

        $set = (new RoutineProgression($user))->build($routine)['payload']['routine']['exercises'][0]['sets'][0];

        $this->assertEquals(40.0, $set['weight_kg']);
        $this->assertSame(10, $set['reps']);
    }

    public function test_a_missed_prescription_is_repeated_not_raised(): void
    {
        $user = User::factory()->create();
        $routine = $this->routineWith($user, [
            ['type' => 'normal', 'weight_kg' => 80, 'reps' => 8],
        ]);

        // Last session: only 6 of the prescribed 8 at 80 kg.
        $this->seedWorkout($user, Carbon::now()->subDays(2), ['BENCH'], 1, 80.0, 6, rpe: 9.0);

        $result = (new RoutineProgression($user))->build($routine);
        $set = $result['payload']['routine']['exercises'][0]['sets'][0];

        $this->assertEquals(80.0, $set['weight_kg']);
        $this->assertSame(8, $set['reps']);
        $this->assertStringContainsString('came up short', $result['changes'][0]);
    }

    public function test_meeting_the_prescription_at_grinding_rpe_holds(): void
    {
        $user = User::factory()->create();
        $routine = $this->routineWith($user, [
            ['type' => 'normal', 'weight_kg' => 80, 'reps' => 8],
        ]);

        $this->seedWorkout($user, Carbon::now()->subDays(2), ['BENCH'], 1, 80.0, 8, rpe: 10.0);

        $result = (new RoutineProgression($user))->build($routine);
        $set = $result['payload']['routine']['exercises'][0]['sets'][0];

        $this->assertSame(8, $set['reps']);
        $this->assertStringContainsString('consolidate', $result['changes'][0]);
    }

    public function test_meeting_the_prescription_comfortably_progresses(): void
    {
        $user = User::factory()->create();
        $routine = $this->routineWith($user, [
            ['type' => 'normal', 'weight_kg' => 80, 'reps' => 8],
        ]);

        $this->seedWorkout($user, Carbon::now()->subDays(2), ['BENCH'], 1, 80.0, 8, rpe: 8.0);

        $set = (new RoutineProgression($user))->build($routine)['payload']['routine']['exercises'][0]['sets'][0];

        $this->assertSame(9, $set['reps']);
    }

    public function test_training_lighter_than_prescribed_holds_the_prescription(): void
    {
        $user = User::factory()->create();
        $routine = $this->routineWith($user, [
            ['type' => 'normal', 'weight_kg' => 80, 'reps' => 8],
        ]);

        // The prescribed load was never touched last session.
        $this->seedWorkout($user, Carbon::now()->subDays(2), ['BENCH'], 3, 70.0, 10, rpe: 8.0);

        $set = (new RoutineProgression($user))->build($routine)['payload']['routine']['exercises'][0]['sets'][0];

        $this->assertEquals(80.0, $set['weight_kg']);
        $this->assertSame(8, $set['reps']);
    }

    public function test_an_older_session_beyond_the_window_is_ignored(): void
    {
        $user = User::factory()->create();
        $routine = $this->routineWith($user, [
            ['type' => 'normal', 'weight_kg' => 80, 'reps' => 8],
        ]);

        // A miss from two months ago says nothing about next week.
        $this->seedWorkout($user, Carbon::now()->subDays(60), ['BENCH'], 1, 80.0, 5, rpe: 9.0);

        $set = (new RoutineProgression($user))->build($routine)['payload']['routine']['exercises'][0]['sets'][0];

        $this->assertSame(9, $set['reps']);
    }
}
