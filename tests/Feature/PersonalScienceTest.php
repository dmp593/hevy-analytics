<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Services\Analytics\PersonalScience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

/**
 * The personal-science trio: recovery curve, time-of-day strength and the
 * rep-range portfolio — all honest about sample sizes, all silent when
 * the data cannot support an answer.
 */
class PersonalScienceTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    public function test_the_recovery_curve_groups_by_rest_days(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['bp' => ['Bench Press', 'chest', []]]);

        // Alternating 2-day and 3-day rests; sessions AFTER a 3-day rest
        // lift 5% heavier — the weight must follow the rest actually taken.
        $day = Carbon::now()->subDays(60)->startOfDay()->setHour(18);
        $rest = null;
        foreach (range(1, 12) as $i) {
            $weight = $rest === 3 ? 105.0 : 100.0;
            $this->seedWorkout($user, $day->copy(), ['bp'], 2, $weight, 5, rpe: 8.0);
            $rest = $i % 2 === 0 ? 3 : 2;
            $day = $day->copy()->addDays($rest);
        }

        $curve = (new PersonalScience($user))->recoveryCurve();

        $this->assertNotNull($curve);
        $byDays = collect($curve['buckets'])->keyBy('days');
        $this->assertTrue($byDays->has('2') && $byDays->has('3'));
        // Three rest days read stronger than two, as seeded.
        $this->assertGreaterThan($byDays['2']['mean_rel'], $byDays['3']['mean_rel']);
        $this->assertGreaterThanOrEqual(3, $byDays['2']['n']);
        $this->assertContains('bp', $curve['lifts']);
    }

    public function test_too_few_sessions_yield_no_curve(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['bp' => ['Bench Press', 'chest', []]]);
        foreach ([2, 4, 6] as $d) {
            $this->seedWorkout($user, Carbon::now()->subDays($d), ['bp'], 2, 100.0, 5, rpe: 8.0);
        }

        $this->assertNull((new PersonalScience($user))->recoveryCurve());
    }

    public function test_time_of_day_reports_both_slots_when_trained_in_both(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);

        // Five morning sessions at 100, five evening sessions at 106.
        foreach (range(1, 5) as $i) {
            $this->seedWorkout($user, Carbon::now()->subDays($i * 9)->setHour(7), ['sq'], 2, 100.0, 5, rpe: 8.0);
            $this->seedWorkout($user, Carbon::now()->subDays($i * 9 + 4)->setHour(19), ['sq'], 2, 106.0, 5, rpe: 8.0);
        }

        $tod = (new PersonalScience($user))->timeOfDay();

        $this->assertNotNull($tod);
        $this->assertGreaterThan($tod['buckets']['morning']['mean_rel'], $tod['buckets']['evening']['mean_rel']);
        $this->assertSame(1, $tod['lifts']);
    }

    public function test_one_time_slot_only_stays_silent(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);
        foreach (range(1, 10) as $i) {
            $this->seedWorkout($user, Carbon::now()->subDays($i * 5)->setHour(19), ['sq'], 2, 100.0, 5, rpe: 8.0);
        }

        $this->assertNull((new PersonalScience($user))->timeOfDay());
    }

    public function test_the_portfolio_shares_sum_and_flag_a_heavy_gap_for_strength_goals(): void
    {
        $user = $this->makeAthlete();
        Goal::factory()->for($user)->create(['type' => 'strength']);
        $this->seedExerciseTemplates($user, ['bp' => ['Bench Press', 'chest', []]]);

        // All sets at 10 reps: 100% in the 6-12 band, none heavy.
        foreach (range(1, 4) as $i) {
            $this->seedWorkout($user, Carbon::now()->subWeeks($i), ['bp'], 4, 80.0, 10, rpe: 8.0);
        }

        $portfolio = (new PersonalScience($user))->repRangePortfolio();

        $this->assertNotNull($portfolio);
        $chest = collect($portfolio['muscles'])->firstWhere('muscle', 'chest');
        $this->assertSame(100, $chest['bands']['b6_12']);
        $this->assertSame(0, $chest['bands']['b1_5']);
        $this->assertTrue($portfolio['strength_gap']);
    }

    public function test_the_pages_render_the_science_sections(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['bp' => ['Bench Press', 'chest', []]]);
        // Alternating rests so the curve has two buckets and renders.
        $day = Carbon::now()->subDays(45)->startOfDay()->setHour(18);
        foreach (range(1, 12) as $i) {
            $this->seedWorkout($user, $day->copy(), ['bp'], 3, 100.0, 8, rpe: 8.0);
            $day = $day->copy()->addDays($i % 2 === 0 ? 3 : 2);
        }

        $this->actingAs($user)->get('/performance')->assertOk()
            ->assertSee(__('app.science.recovery_title'));
        $this->actingAs($user)->get('/muscle')->assertOk()
            ->assertSee(__('app.science.portfolio_title'));
    }
}
