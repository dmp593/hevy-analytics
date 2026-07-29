<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\User;
use App\Services\Analytics\GoalAlerts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

/**
 * The training-load alerts: a sharp week-on-week volume ramp, and top lifts
 * whose e1RM has stopped moving. Both read the workout log directly, so the
 * fixtures build real sessions rather than stubbing the analytics.
 */
class GoalAlertsTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    /** @return array<int, string> */
    private function titles(User $user): array
    {
        return array_column((new GoalAlerts($user))->all(), 'title');
    }

    /** Four weeks of ~9 hard sets/week ending just outside the recent window. */
    private function seedBaselineMonth(User $user): void
    {
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);

        foreach (range(2, 5) as $week) {
            foreach ([1, 3, 5] as $offset) {
                $this->seedWorkout($user, Carbon::now()->subWeeks($week)->addDays($offset), ['sq'], 3);
            }
        }
    }

    public function test_a_sharp_volume_ramp_is_flagged(): void
    {
        $user = $this->makeAthlete();
        $this->seedBaselineMonth($user);

        // 18 sets this week against a ~9/week baseline: past the 1.6x line.
        foreach ([1, 3] as $daysAgo) {
            $this->seedWorkout($user, Carbon::now()->subDays($daysAgo), ['sq'], 9);
        }

        $this->assertContains(__('app.alerts.volume_spike'), $this->titles($user));
    }

    public function test_a_steady_week_is_not_called_a_spike(): void
    {
        $user = $this->makeAthlete();
        $this->seedBaselineMonth($user);

        $this->seedWorkout($user, Carbon::now()->subDays(2), ['sq'], 9);

        $this->assertNotContains(__('app.alerts.volume_spike'), $this->titles($user));
    }

    public function test_a_new_trainee_ramping_up_is_not_accused_of_spiking(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);

        // Two weeks of history, then a big week: that is starting to train,
        // not a load spike, and the baseline guard must refuse to judge it.
        foreach ([16, 12, 9] as $daysAgo) {
            $this->seedWorkout($user, Carbon::now()->subDays($daysAgo), ['sq'], 3);
        }
        foreach ([1, 3] as $daysAgo) {
            $this->seedWorkout($user, Carbon::now()->subDays($daysAgo), ['sq'], 9);
        }

        $this->assertNotContains(__('app.alerts.volume_spike'), $this->titles($user));
    }

    public function test_a_flat_top_lift_over_eight_weeks_is_reported_by_name(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['bp' => ['Bench Press', 'chest', []]]);

        foreach (range(1, 8) as $week) {
            $this->seedWorkout($user, Carbon::now()->subWeeks($week)->addDay(), ['bp'], 3, 100.0, 5, rpe: 8.0);
        }

        $alerts = (new GoalAlerts($user))->all();
        $stall = collect($alerts)->firstWhere('title', __('app.alerts.stalled_lifts'));

        $this->assertNotNull($stall);
        $this->assertStringContainsString('bp', $stall['message']);
    }

    public function test_a_climbing_lift_is_not_called_stalled(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['bp' => ['Bench Press', 'chest', []]]);

        foreach (range(1, 8) as $week) {
            $this->seedWorkout($user, Carbon::now()->subWeeks($week)->addDay(), ['bp'], 3, 100.0 + 2 * (8 - $week), 5, rpe: 8.0);
        }

        $this->assertNotContains(__('app.alerts.stalled_lifts'), $this->titles($user));
    }

    public function test_a_cut_suppresses_the_stall_alert(): void
    {
        $user = $this->makeAthlete();
        Goal::factory()->for($user)->create(['type' => 'cut']);
        $this->seedExerciseTemplates($user, ['bp' => ['Bench Press', 'chest', []]]);

        foreach (range(1, 8) as $week) {
            $this->seedWorkout($user, Carbon::now()->subWeeks($week)->addDay(), ['bp'], 3, 100.0, 5, rpe: 8.0);
        }

        // Holding strength in a deficit is success, not a stall.
        $this->assertNotContains(__('app.alerts.stalled_lifts'), $this->titles($user));
    }

    public function test_five_sessions_are_too_few_to_call_a_stall(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['bp' => ['Bench Press', 'chest', []]]);

        foreach (range(1, 5) as $week) {
            $this->seedWorkout($user, Carbon::now()->subWeeks($week)->addDay(), ['bp'], 3, 100.0, 5, rpe: 8.0);
        }

        $this->assertNotContains(__('app.alerts.stalled_lifts'), $this->titles($user));
    }
}
