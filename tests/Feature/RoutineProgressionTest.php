<?php

namespace Tests\Feature;

use App\Models\Routine;
use App\Models\User;
use App\Services\Hevy\RoutineProgression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoutineProgressionTest extends TestCase
{
    use RefreshDatabase;

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
}
