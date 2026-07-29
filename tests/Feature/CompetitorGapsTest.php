<?php

namespace Tests\Feature;

use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\MuscleOverload;
use App\Services\Analytics\StrengthAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

/**
 * The status board and per-muscle overload: presentation over the same
 * regressions the alerts use, plus the calendar-style preference.
 */
class CompetitorGapsTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    private function seedTwoLifts($user): void
    {
        $this->seedExerciseTemplates($user, [
            'bp' => ['Bench Press', 'chest', []],
            'sq' => ['Squat', 'quadriceps', []],
        ]);

        foreach (range(1, 8) as $week) {
            // Bench climbs 2 kg/week; squat stays put.
            $this->seedWorkout($user, Carbon::now()->subWeeks($week)->addDay(), ['bp'], 3, 80.0 + 2 * (8 - $week), 5, rpe: 8.0);
            $this->seedWorkout($user, Carbon::now()->subWeeks($week)->addDays(3), ['sq'], 3, 100.0, 5, rpe: 8.0);
        }
    }

    public function test_the_status_board_ranks_worst_first_with_pct_rates(): void
    {
        $user = $this->makeAthlete();
        $this->seedTwoLifts($user);

        $board = (new StrengthAnalytics($user, new FilterCriteria(
            from: Carbon::now()->subWeeks(8), to: Carbon::now(),
        )))->exerciseStatusBoard();

        $this->assertCount(2, $board);
        // Flat squat ranks before climbing bench: triage order.
        $this->assertSame('sq', $board[0]['exercise']);
        $this->assertSame('flat', $board[0]['direction']);
        $this->assertSame('up', $board[1]['direction']);
        $this->assertGreaterThan(1.0, $board[1]['pct_per_week']);
    }

    public function test_overload_aggregates_per_muscle_with_stated_weights(): void
    {
        $user = $this->makeAthlete();
        $this->seedTwoLifts($user);

        $overload = (new MuscleOverload($user))->perMuscle();

        $byMuscle = collect($overload)->keyBy('muscle');
        $this->assertSame('up', $byMuscle['chest']['direction']);
        $this->assertSame('flat', $byMuscle['quadriceps']['direction']);
        // Sorted worst-first.
        $this->assertSame('quadriceps', $overload[0]['muscle']);
    }

    public function test_the_pages_render_both_cards(): void
    {
        $user = $this->makeAthlete();
        $this->seedTwoLifts($user);

        $this->actingAs($user)->get('/performance')->assertOk()
            ->assertSee(__('app.board.title'));
        $this->actingAs($user)->get('/muscle')->assertOk()
            ->assertSee(__('app.overload.title'));
    }

    public function test_the_calendar_style_switch_persists_and_changes_the_view(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);
        $this->seedWorkout($user, Carbon::now()->subDay(), ['sq'], 3);

        $this->actingAs($user)->post('/settings/calendar/classic')->assertRedirect();
        $this->assertSame('classic', $user->fresh()->calendar_style);

        // Classic view shows month names instead of the contribution grid.
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee(Carbon::now()->isoFormat('MMMM YYYY'));

        $this->actingAs($user)->post('/settings/calendar/nonsense')->assertNotFound();
    }
}
