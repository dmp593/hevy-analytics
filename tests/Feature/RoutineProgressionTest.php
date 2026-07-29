<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\User;
use App\Science\Strength\OneRepMax;
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

    public function test_grinding_at_a_stalled_lift_earns_a_back_off(): void
    {
        $user = User::factory()->create();
        $routine = $this->routineWith($user, [
            ['type' => 'normal', 'weight_kg' => 100, 'reps' => 5],
        ]);

        // Eight weeks of the same bar: a flat trend by the same definition
        // the dashboard alert uses, ending in a session ground out at RPE 10.
        foreach (range(2, 8) as $week) {
            $this->seedWorkout($user, Carbon::now()->subWeeks($week)->addDay(), ['BENCH'], 3, 100.0, 5, rpe: 8.0);
        }
        $this->seedWorkout($user, Carbon::now()->subDays(2), ['BENCH'], 1, 100.0, 5, rpe: 10.0);

        $result = (new RoutineProgression($user))->build($routine);
        $set = $result['payload']['routine']['exercises'][0]['sets'][0];

        $this->assertEquals(92.5, $set['weight_kg']);
        $this->assertSame(5, $set['reps']);
        $this->assertStringContainsString('back off', $result['changes'][0]);
    }

    public function test_a_rep_range_without_load_is_prescribed_at_the_bottom_with_reps_in_reserve(): void
    {
        $user = User::factory()->create();
        $routine = $this->routineWith($user, [
            ['type' => 'normal', 'rep_range' => ['start' => 8, 'end' => 12]],
        ]);

        // Recent history gives the lift an e1RM to prescribe from.
        $this->seedWorkout($user, Carbon::now()->subDays(3), ['BENCH'], 3, 100.0, 5, rpe: 8.0);

        $result = (new RoutineProgression($user))->build($routine);
        $set = $result['payload']['routine']['exercises'][0]['sets'][0];

        // Bottom of the range, with a 2-rep reserve discount: the prescribed
        // load must be BELOW the predicted 8RM, not above it.
        $e1rm = OneRepMax::estimate(100.0, 5, 8.0);
        $eightRepMax = OneRepMax::loadForReps($e1rm, 8);

        $this->assertLessThan($eightRepMax, $set['weight_kg']);
        $this->assertStringContainsString('~2 in reserve', $result['changes'][0]);
    }

    public function test_a_planned_load_is_never_overwritten_by_the_range_branch(): void
    {
        $user = User::factory()->create();
        $routine = $this->routineWith($user, [
            ['type' => 'normal', 'weight_kg' => 60, 'reps' => 10, 'rep_range' => ['start' => 8, 'end' => 12]],
        ]);

        $this->seedWorkout($user, Carbon::now()->subDays(3), ['BENCH'], 3, 60.0, 10, rpe: 8.0);

        $set = (new RoutineProgression($user))->build($routine)['payload']['routine']['exercises'][0]['sets'][0];

        // Double progression applies (60x10 -> 60x11); the e1RM prescription
        // must not clobber the planned 60 kg with a ~115 kg "suggestion".
        $this->assertEquals(60.0, $set['weight_kg']);
        $this->assertSame(11, $set['reps']);
    }
}
