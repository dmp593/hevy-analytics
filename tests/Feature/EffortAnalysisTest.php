<?php

namespace Tests\Feature;

use App\Services\Analytics\EffortAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

/**
 * The effort read: muscles trained mostly far from failure are flagged, and
 * thin RPE coverage silences the whole section instead of guessing.
 */
class EffortAnalysisTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    public function test_thin_rpe_coverage_stays_silent(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);

        // Plenty of sets, none carrying an RPE.
        $this->seedWorkout($user, Carbon::now()->subDays(3), ['sq'], 12);

        $summary = (new EffortAnalysis($user))->summary();

        $this->assertFalse($summary['enough']);
        $this->assertSame(0, $summary['coverage_pct']);
        $this->assertSame([], $summary['flagged']);
    }

    public function test_a_muscle_trained_far_from_failure_is_flagged_by_share(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, [
            'bp' => ['Bench Press', 'chest', []],
            'sq' => ['Squat', 'quadriceps', []],
        ]);

        // Chest: 8 sets at RPE 6 (4+ reps in reserve). Quads: 8 sets at RPE 8.5.
        $this->seedWorkout($user, Carbon::now()->subDays(4), ['bp'], 8, 60.0, 10, rpe: 6.0);
        $this->seedWorkout($user, Carbon::now()->subDays(2), ['sq'], 8, 100.0, 8, rpe: 8.5);

        $summary = (new EffortAnalysis($user))->summary();

        $this->assertTrue($summary['enough']);
        $this->assertSame(100, $summary['coverage_pct']);
        $this->assertCount(1, $summary['flagged']);
        $this->assertSame('chest', $summary['flagged'][0]['muscle']);
        $this->assertSame(100, $summary['flagged'][0]['far_pct']);
    }

    public function test_the_muscle_page_shows_the_flag(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['bp' => ['Bench Press', 'chest', []]]);
        $this->seedWorkout($user, Carbon::now()->subDays(4), ['bp'], 12, 60.0, 10, rpe: 6.0);

        $this->actingAs($user)->get('/muscle')
            ->assertOk()
            ->assertSee(__('app.effort.title'))
            ->assertSee(__('app.effort.far_share', ['pct' => 100, 'sets' => 12]));
    }

    public function test_close_to_failure_training_reads_as_such(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['sq' => ['Squat', 'quadriceps', []]]);
        $this->seedWorkout($user, Carbon::now()->subDays(2), ['sq'], 12, 100.0, 8, rpe: 8.5);

        $summary = (new EffortAnalysis($user))->summary();

        $this->assertTrue($summary['enough']);
        $this->assertSame([], $summary['flagged']);
    }
}
