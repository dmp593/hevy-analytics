<?php

namespace Tests\Feature;

use App\Services\Analytics\ConsistencyAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

/**
 * The consistency card: sessions, streak, and per-muscle frequency, counted
 * from real workout rows in the athlete's timezone.
 */
class ConsistencyTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    public function test_summary_counts_sessions_streak_and_muscle_frequency(): void
    {
        // A fixed Wednesday, so "this week" does not depend on the day the
        // suite happens to run.
        Carbon::setTestNow('2026-07-29 12:00:00');

        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, [
            'sq' => ['Squat', 'quadriceps', []],
            'bp' => ['Bench Press', 'chest', []],
        ]);

        // Four full weeks before this one: quads twice a week, chest once.
        foreach (range(1, 4) as $w) {
            $monday = Carbon::now()->startOfWeek()->subWeeks($w);
            $this->seedWorkout($user, $monday->copy()->addDays(1)->setHour(18), ['sq', 'bp'], 3);
            $this->seedWorkout($user, $monday->copy()->addDays(4)->setHour(18), ['sq'], 3);
        }

        // This week: one session so far, yesterday.
        $this->seedWorkout($user, Carbon::now()->subDay()->setHour(18), ['sq', 'bp'], 3);

        $summary = (new ConsistencyAnalytics($user))->summary();

        $this->assertSame(1, $summary['sessions_this_week']);
        $this->assertSame(5, $summary['streak_weeks']);
        $this->assertSame(2, $summary['muscles_trained']);
        // Quads hit ~2x/week (8 training days in 28); chest only ~1x (4 days).
        $this->assertSame(1, $summary['muscles_at_frequency']);
        $this->assertFalse($summary['early_days']);
    }

    public function test_a_quiet_current_week_does_not_break_the_streak(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00');

        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);

        foreach (range(1, 3) as $w) {
            $this->seedWorkout($user, Carbon::now()->startOfWeek()->subWeeks($w)->addDays(2)->setHour(18), ['sq'], 3);
        }

        $summary = (new ConsistencyAnalytics($user))->summary();

        $this->assertSame(0, $summary['sessions_this_week']);
        $this->assertSame(3, $summary['streak_weeks']);
    }

    public function test_a_new_account_is_in_its_early_days(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00');

        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);
        $this->seedWorkout($user, Carbon::now()->subDays(5), ['sq'], 3);

        $this->assertTrue((new ConsistencyAnalytics($user))->summary()['early_days']);
    }

    public function test_no_workouts_means_no_card(): void
    {
        $this->assertNull((new ConsistencyAnalytics($this->makeAthlete()))->summary());
    }

    public function test_the_dashboard_renders_the_card(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00');

        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);
        $this->seedWorkout($user, Carbon::now()->subDay(), ['sq'], 3);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee(__('app.consistency.title'))
            ->assertSee(__('app.consistency.streak'));
    }
}
