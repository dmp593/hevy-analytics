<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Hevy\RoutineAdvisor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

/**
 * The advisor suggests SMALL routine adjustments from evidence: a muscle
 * below its MEV gets one exercise added to the compatible active routine; an
 * exercise in real e1RM decline gets a stimulus swap. Both become staged
 * write operations — nothing edits a routine on its own.
 */
class RoutineAdvisorTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    /**
     * A leg routine trained weekly with calves never touched, plus a push
     * routine. The calf suggestion belongs on the leg day.
     */
    private function seedLegProgramme(User $user): void
    {
        $this->seedExerciseTemplates($user, [
            'sq' => ['Squat (Barbell)', 'quadriceps', ['glutes']],
            'lc' => ['Lying Leg Curl (Machine)', 'hamstrings', []],
            'bp' => ['Bench Press (Barbell)', 'chest', ['triceps']],
            'cr' => ['Standing Calf Raise', 'calves', []],
        ]);

        $legs = $user->routines()->create(['hevy_id' => 'r-legs', 'title' => 'Treino C - Pernas', 'hevy_created_at' => now()->subMonths(2)]);
        $legs->exercises()->create(['index' => 0, 'title' => 'Squat (Barbell)', 'exercise_template_hevy_id' => 'sq', 'sets' => [['type' => 'normal', 'weight_kg' => 100, 'reps' => 5]]]);
        $legs->exercises()->create(['index' => 1, 'title' => 'Lying Leg Curl (Machine)', 'exercise_template_hevy_id' => 'lc', 'sets' => [['type' => 'normal', 'weight_kg' => 40, 'reps' => 10]]]);

        $push = $user->routines()->create(['hevy_id' => 'r-push', 'title' => 'Treino A - Push', 'hevy_created_at' => now()->subMonths(2)]);
        $push->exercises()->create(['index' => 0, 'title' => 'Bench Press (Barbell)', 'exercise_template_hevy_id' => 'bp', 'sets' => [['type' => 'normal', 'weight_kg' => 80, 'reps' => 8]]]);

        foreach ([1, 8, 15] as $daysAgo) {
            $this->seedLinkedWorkout($user, Carbon::now()->subDays($daysAgo), 'r-legs', ['sq', 'lc']);
            $this->seedLinkedWorkout($user, Carbon::now()->subDays($daysAgo + 2), 'r-push', ['bp']);
        }
    }

    /** A workout attributed to a routine, 3 working sets per exercise. */
    private function seedLinkedWorkout(User $user, Carbon $date, string $routineHevyId, array $templateIds, float $weight = 80.0, int $reps = 8): void
    {
        $workoutId = $this->seedWorkout($user, $date, $templateIds, 3, $weight, $reps);
        DB::table('workouts')->where('id', $workoutId)->update(['routine_hevy_id' => $routineHevyId]);
    }

    public function test_untrained_muscle_gets_an_add_suggestion_on_the_compatible_routine(): void
    {
        $user = $this->makeAthlete();
        $this->seedLegProgramme($user);

        $suggestions = (new RoutineAdvisor($user))->suggestions();
        $adds = array_values(array_filter($suggestions, fn ($s) => $s['type'] === 'add' && $s['muscle'] === 'calves'));

        $this->assertNotEmpty($adds, 'calves at zero sets should produce an add suggestion');
        $this->assertSame('r-legs', $adds[0]['routine_hevy_id']);
        $this->assertSame('Standing Calf Raise', $adds[0]['template_title']);
        $this->assertSame(0.0, $adds[0]['per_week']);
    }

    public function test_no_suggestions_without_active_routines(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['cr' => ['Standing Calf Raise', 'calves', []]]);
        $user->routines()->create(['hevy_id' => 'r-old', 'title' => 'Antigo']);

        $this->assertSame([], (new RoutineAdvisor($user))->suggestions());
    }

    public function test_muscle_with_no_compatible_session_is_not_forced_anywhere(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, [
            'bp' => ['Bench Press (Barbell)', 'chest', ['triceps']],
            'cr' => ['Standing Calf Raise', 'calves', []],
        ]);

        $push = $user->routines()->create(['hevy_id' => 'r-push', 'title' => 'Push']);
        $push->exercises()->create(['index' => 0, 'title' => 'Bench Press (Barbell)', 'exercise_template_hevy_id' => 'bp', 'sets' => []]);

        foreach ([2, 9, 16] as $daysAgo) {
            $this->seedLinkedWorkout($user, Carbon::now()->subDays($daysAgo), 'r-push', ['bp']);
        }

        $calves = array_filter((new RoutineAdvisor($user))->suggestions(), fn ($s) => ($s['muscle'] ?? null) === 'calves');

        $this->assertEmpty($calves, 'calves must not be forced into a push-only programme');
    }

    /**
     * The exclusion runs on template id, not just title: Hevy stores a null
     * title on some synced routine exercises, and a title-only check would
     * suggest adding an exercise the routine already prescribes.
     */
    public function test_exercise_already_in_the_routine_is_never_suggested_again(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, [
            'sq' => ['Squat (Barbell)', 'quadriceps', []],
            'cr' => ['Standing Calf Raise', 'calves', []],
        ]);

        $legs = $user->routines()->create(['hevy_id' => 'r-legs', 'title' => 'Pernas']);
        $legs->exercises()->create(['index' => 0, 'title' => 'Squat (Barbell)', 'exercise_template_hevy_id' => 'sq', 'sets' => [['type' => 'normal', 'weight_kg' => 100, 'reps' => 5]]]);
        // One calf exercise already prescribed — but with no title, as Hevy
        // sometimes returns.
        $legs->exercises()->create(['index' => 1, 'title' => null, 'exercise_template_hevy_id' => 'cr', 'sets' => [['type' => 'normal', 'weight_kg' => 60, 'reps' => 12]]]);

        foreach ([1, 8, 15] as $daysAgo) {
            $this->seedLinkedWorkout($user, Carbon::now()->subDays($daysAgo), 'r-legs', ['sq']);
        }

        $adds = array_filter((new RoutineAdvisor($user))->suggestions(), fn ($s) => ($s['template_hevy_id'] ?? null) === 'cr');

        $this->assertEmpty($adds, 'an exercise already in the routine must not be suggested');
    }

    public function test_declining_lift_gets_a_swap_with_different_equipment(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, [
            'bp' => ['Bench Press (Barbell)', 'chest', ['triceps']],
            'cp' => ['Chest Press (Machine)', 'chest', ['triceps']],
        ]);
        DB::table('exercise_templates')->where('hevy_id', 'bp')->update(['equipment' => 'barbell']);
        DB::table('exercise_templates')->where('hevy_id', 'cp')->update(['equipment' => 'machine']);

        $push = $user->routines()->create(['hevy_id' => 'r-push', 'title' => 'Push']);
        $push->exercises()->create(['index' => 0, 'title' => 'Bench Press (Barbell)', 'exercise_template_hevy_id' => 'bp', 'sets' => [['type' => 'normal', 'weight_kg' => 80, 'reps' => 8]]]);

        // Eight weekly sessions sliding from 96 kg to 75 kg: a steep,
        // consistent decline no noise band can excuse.
        foreach (range(0, 7) as $week) {
            $this->seedLinkedWorkout($user, Carbon::now()->subWeeks(7 - $week)->subHours(3), 'r-push', ['bp'], 96 - $week * 3.0, 1);
        }

        $swaps = array_values(array_filter((new RoutineAdvisor($user))->suggestions(), fn ($s) => $s['type'] === 'swap'));

        $this->assertNotEmpty($swaps, 'a real 8-week decline should produce a swap suggestion');
        $this->assertSame('Bench Press (Barbell)', $swaps[0]['template_title']);
        $this->assertSame('Chest Press (Machine)', $swaps[0]['alternative_title']);
        $this->assertLessThan(0, $swaps[0]['pct_per_week']);
    }

    public function test_flat_lift_never_triggers_a_swap(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, [
            'bp' => ['Bench Press (Barbell)', 'chest', ['triceps']],
            'cp' => ['Chest Press (Machine)', 'chest', ['triceps']],
        ]);

        $push = $user->routines()->create(['hevy_id' => 'r-push', 'title' => 'Push']);
        $push->exercises()->create(['index' => 0, 'title' => 'Bench Press (Barbell)', 'exercise_template_hevy_id' => 'bp', 'sets' => [['type' => 'normal', 'weight_kg' => 80, 'reps' => 8]]]);

        // Same load week after week: a stall — the back-off's territory, not
        // the swap's.
        foreach (range(0, 7) as $week) {
            $this->seedLinkedWorkout($user, Carbon::now()->subWeeks(7 - $week)->subHours(3), 'r-push', ['bp'], 80, 5);
        }

        $swaps = array_filter((new RoutineAdvisor($user))->suggestions(), fn ($s) => $s['type'] === 'swap');

        $this->assertEmpty($swaps);
    }

    public function test_advice_renders_on_the_routines_page_and_stages_on_confirm(): void
    {
        $user = $this->makeAthlete();
        $this->seedLegProgramme($user);

        $this->actingAs($user)->get(route('routines'))
            ->assertOk()
            ->assertSee(__('app.advisor.title'))
            ->assertSee('Standing Calf Raise');

        $this->actingAs($user)->post(route('write.adjustment', 'r-legs'), [
            'action' => 'add',
            'template' => 'cr',
        ])->assertRedirect(route('write.index'));

        $op = $user->writeOperations()->latest('id')->first();
        $this->assertSame('routine.update', $op->operation);
        $this->assertSame('pending', $op->status);

        $exercises = $op->payload['routine']['exercises'];
        $this->assertCount(3, $exercises, 'the two existing exercises plus the new one');

        $new = end($exercises);
        $this->assertSame('cr', $new['exercise_template_id']);
        $this->assertCount(2, $new['sets']);
        $this->assertNull($new['sets'][0]['weight_kg'], 'a movement with no history gets no invented load');
        $this->assertSame(['start' => 8, 'end' => 12], $new['sets'][0]['rep_range']);
    }

    public function test_swap_staging_replaces_the_exercise_and_keeps_working_set_count(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, [
            'bp' => ['Bench Press (Barbell)', 'chest', ['triceps']],
            'cp' => ['Chest Press (Machine)', 'chest', ['triceps']],
        ]);

        $push = $user->routines()->create(['hevy_id' => 'r-push', 'title' => 'Push']);
        $push->exercises()->create(['index' => 0, 'title' => 'Bench Press (Barbell)', 'exercise_template_hevy_id' => 'bp', 'rest_seconds' => 150, 'sets' => [
            ['type' => 'warmup', 'weight_kg' => 40, 'reps' => 10],
            ['type' => 'normal', 'weight_kg' => 80, 'reps' => 8],
            ['type' => 'normal', 'weight_kg' => 80, 'reps' => 8],
            ['type' => 'normal', 'weight_kg' => 80, 'reps' => 8],
        ]]);

        $this->actingAs($user)->post(route('write.adjustment', 'r-push'), [
            'action' => 'swap',
            'template' => 'cp',
            'replace' => 'bp',
        ])->assertRedirect(route('write.index'));

        $exercises = $user->writeOperations()->latest('id')->first()->payload['routine']['exercises'];

        $this->assertCount(1, $exercises);
        $this->assertSame('cp', $exercises[0]['exercise_template_id']);
        $this->assertSame(150, $exercises[0]['rest_seconds'], 'the slot keeps its rest time');
        $this->assertCount(3, $exercises[0]['sets'], 'working sets survive; the old load does not');
        $this->assertNull($exercises[0]['sets'][0]['weight_kg']);
    }

    /**
     * Two pending snapshots of one routine describe two futures that both
     * start from today — confirming both would silently undo the first. One
     * pending change per routine makes that impossible.
     */
    public function test_staging_a_second_change_supersedes_the_first_for_that_routine(): void
    {
        $user = $this->makeAthlete();
        $this->seedLegProgramme($user);

        $this->actingAs($user)->post(route('write.adjustment', 'r-legs'), ['action' => 'add', 'template' => 'cr']);
        $first = $user->writeOperations()->latest('id')->first();

        $response = $this->actingAs($user)->post(route('write.progression', 'r-legs'));
        $second = $user->writeOperations()->latest('id')->first();

        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame('pending', $second->status);
        $this->assertNotSame($first->id, $second->id);
        $response->assertSessionHas('status', __('app.write.staged_replaced', ['routine' => 'Treino C - Pernas']));

        // A pending change for a DIFFERENT routine is untouched.
        $this->actingAs($user)->post(route('write.progression', 'r-push'));
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_adjustment_endpoint_rejects_other_peoples_routines(): void
    {
        $owner = $this->makeAthlete();
        $intruder = $this->makeAthlete();
        $this->seedExerciseTemplates($intruder, ['cr' => ['Standing Calf Raise', 'calves', []]]);

        $owner->routines()->create(['hevy_id' => 'r-owner', 'title' => 'Meu']);

        $this->actingAs($intruder)->post(route('write.adjustment', 'r-owner'), [
            'action' => 'add',
            'template' => 'cr',
        ])->assertForbidden();

        $this->assertSame(0, $owner->writeOperations()->count());
    }
}
