<?php

namespace Tests\Feature;

use App\Services\Analytics\TrainingRhythm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

/**
 * The training calendar and rhythm read: sets per day for the heatmap,
 * median session length, and the hours/weekdays training actually happens.
 */
class TrainingRhythmTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    public function test_summary_counts_days_durations_and_habits(): void
    {
        Carbon::setTestNow('2026-07-29 12:00:00');

        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);

        // Three one-hour Monday-evening sessions and one Thursday morning.
        foreach ([1, 2, 3] as $w) {
            $this->seedWorkout($user, Carbon::now()->startOfWeek()->subWeeks($w)->setHour(19), ['sq'], 4);
        }
        $this->seedWorkout($user, Carbon::now()->startOfWeek()->subWeeks(1)->addDays(3)->setHour(7), ['sq'], 2);

        $summary = (new TrainingRhythm($user))->summary();

        $this->assertSame(4, $summary['sessions']);
        $this->assertSame(60, $summary['median_duration_min']);
        $this->assertContains(19, $summary['top_hours']);
        // ISO weekday 1 = Monday, the dominant day.
        $this->assertSame(1, $summary['top_weekdays'][0]);

        $monday = Carbon::now()->startOfWeek()->subWeeks(1)->toDateString();
        $this->assertSame(4, $summary['days'][$monday]);
        $this->assertSame(4, $summary['max']);
    }

    public function test_no_workouts_means_no_heatmap(): void
    {
        $this->assertNull((new TrainingRhythm($this->makeAthlete()))->summary());
    }

    public function test_the_dashboard_renders_the_heatmap(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);
        $this->seedWorkout($user, Carbon::now()->subDay(), ['sq'], 3);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee(__('app.rhythm.heatmap_aria'));
    }
}
