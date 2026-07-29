<?php

namespace Tests\Feature;

use App\Services\Analytics\BodyCompAnalytics;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\MuscleBalance;
use App\Services\Analytics\ProjectionService;
use App\Services\Analytics\VolumeAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\SeedsTrainingData;
use Tests\TestCase;

/**
 * Regression cover for the analytics layer against NON-EMPTY data.
 *
 * Every one of these guards a defect that shipped: the suite previously only
 * ever exercised the empty-data branch, so each of these bugs was invisible.
 */
class AnalyticsCorrectnessTest extends TestCase
{
    use RefreshDatabase, SeedsTrainingData;

    /**
     * A fixed clock.
     *
     * These fixtures place sessions relative to "now", and one used
     * startOfWeek()->addDay() — a Tuesday. On a Monday that seeds the most
     * recent session for TOMORROW, so it fell outside every window under test
     * and the suite failed one day in seven, months after the code it covers was
     * written. Pinning the date makes any such dependency fail on every run
     * instead of hiding until the calendar lines up.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-06-17 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_weight_trend_reports_a_gain_as_a_gain(): void
    {
        $user = $this->makeAthlete();
        // Unambiguously gaining: +0.25 kg every week for 8 weeks.
        $this->seedWeightTrend($user, startKg: 80.0, kgPerWeek: 0.25, weeks: 8);

        $rate = (new BodyCompAnalytics($user))->weightRateKgPerWeek();

        $this->assertNotNull($rate);
        $this->assertEqualsWithDelta(0.25, $rate['kg_per_week'], 0.02);
        $this->assertGreaterThan(0, $rate['pct_bw_per_week']);
    }

    public function test_weight_trend_reports_a_loss_as_a_loss(): void
    {
        $user = $this->makeAthlete();
        $this->seedWeightTrend($user, startKg: 85.0, kgPerWeek: -0.30, weeks: 8);

        $rate = (new BodyCompAnalytics($user))->weightRateKgPerWeek();

        $this->assertNotNull($rate);
        $this->assertEqualsWithDelta(-0.30, $rate['kg_per_week'], 0.02);
        $this->assertLessThan(0, $rate['pct_bw_per_week']);
    }

    public function test_lean_mass_trend_direction_matches_the_data(): void
    {
        $user = $this->makeAthlete();
        $this->seedWeightTrend($user, startKg: 80.0, kgPerWeek: 0.25, weeks: 12);

        // Fat% held constant, so lean mass must rise with bodyweight.
        $trend = (new BodyCompAnalytics($user))->trendPerMonth('lean_mass');

        $this->assertNotNull($trend);
        $this->assertGreaterThan(0, $trend['per_month']);
    }

    public function test_weekly_sets_per_muscle_divides_by_the_window_not_the_window_plus_one(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['bench' => ['Bench Press', 'chest', []]]);

        // 4 sets of chest in each of 4 consecutive weeks = 4 sets/week exactly.
        for ($week = 3; $week >= 0; $week--) {
            $this->seedWorkout(
                $user,
                Carbon::now()->subWeeks($week)->setTime(18, 0),
                ['bench'],
                setsPerExercise: 4,
            );
        }

        $filter = new FilterCriteria(
            from: Carbon::now()->subDays(28)->startOfDay(),
            to: Carbon::now()->endOfDay(),
        );

        $chest = collect((new VolumeAnalytics($user, $filter))->weeklySetsPerMuscle())
            ->firstWhere('muscle', 'chest');

        $this->assertNotNull($chest);
        $this->assertSame(16.0, $chest['total_sets']);
        // Was 3.2 (16 / 5) before the fix — a 20% understatement that made
        // adequately-trained muscles read as "below maintenance".
        $this->assertEqualsWithDelta(4.0, $chest['per_week'], 0.01);
    }

    public function test_weekly_sets_are_not_diluted_by_a_wide_filter_window(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['bench' => ['Bench Press', 'chest', []]]);

        // Four weeks of training, then viewed through a ten-year window.
        for ($week = 3; $week >= 0; $week--) {
            $this->seedWorkout(
                $user,
                Carbon::now()->subWeeks($week)->setTime(18, 0),
                ['bench'],
                setsPerExercise: 4,
            );
        }

        $wide = new FilterCriteria(
            from: Carbon::now()->subYears(10),
            to: Carbon::now()->endOfDay(),
        );

        $chest = collect((new VolumeAnalytics($user, $wide))->weeklySetsPerMuscle())
            ->firstWhere('muscle', 'chest');

        // Dividing by the requested window rather than the athlete's history
        // reported this as 0.03 sets/week and flagged them "below maintenance".
        $this->assertEqualsWithDelta(4.0, $chest['per_week'], 0.5);
    }

    public function test_a_new_account_is_not_punished_for_having_little_history(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['bench' => ['Bench Press', 'chest', []]]);

        // Three weeks old, viewed through the default six-month window.
        for ($week = 2; $week >= 0; $week--) {
            $this->seedWorkout(
                $user,
                Carbon::now()->subWeeks($week)->setTime(18, 0),
                ['bench'],
                setsPerExercise: 5,
            );
        }

        $default = new FilterCriteria(
            from: Carbon::now()->subMonths(6)->startOfDay(),
            to: Carbon::now()->endOfDay(),
        );

        $chest = collect((new VolumeAnalytics($user, $default))->weeklySetsPerMuscle())
            ->firstWhere('muscle', 'chest');

        // 15 sets across ~3 weeks of training, not across 26 weeks of window.
        $this->assertGreaterThan(3.0, $chest['per_week']);
    }

    public function test_sessions_are_bucketed_in_the_athletes_timezone(): void
    {
        $auckland = $this->makeAthlete(['timezone' => 'Pacific/Auckland']);
        $this->seedExerciseTemplates($auckland, ['bench' => ['Bench Press', 'chest', []]]);

        // 09:00 Monday in Auckland is 21:00 the previous SUNDAY in UTC — so in
        // UTC this session falls in the week before the one the athlete trained.
        $mondayMorningLocal = Carbon::parse('2026-03-09 09:00', 'Pacific/Auckland');
        $this->seedWorkout($auckland, $mondayMorningLocal->copy()->utc(), ['bench']);

        $filter = new FilterCriteria(
            from: Carbon::parse('2026-03-01'),
            to: Carbon::parse('2026-03-31'),
            period: 'week',
        );

        $series = (new VolumeAnalytics($auckland, $filter))->tonnageSeries();

        $this->assertCount(1, $series);
        // Monday 9 March, the week the athlete actually trained — not 2 March.
        $this->assertSame('2026-03-09', $series[0]['label']);
    }

    public function test_the_same_session_buckets_differently_for_a_utc_athlete(): void
    {
        $utc = $this->makeAthlete(['timezone' => 'UTC']);
        $this->seedExerciseTemplates($utc, ['bench' => ['Bench Press', 'chest', []]]);

        $sameInstant = Carbon::parse('2026-03-09 09:00', 'Pacific/Auckland')->utc();
        $this->seedWorkout($utc, $sameInstant, ['bench']);

        $series = (new VolumeAnalytics($utc, new FilterCriteria(
            from: Carbon::parse('2026-03-01'),
            to: Carbon::parse('2026-03-31'),
            period: 'week',
        )))->tonnageSeries();

        // Same instant, different athlete: in UTC it really is the prior week.
        $this->assertSame('2026-03-02', $series[0]['label']);
    }

    public function test_muscle_balance_is_measured_in_sets_not_tonnage(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, [
            'bench' => ['Bench Press', 'chest', []],
            'row' => ['Barbell Row', 'upper_back', []],
        ]);

        // Equal SETS on push and pull every week, but pull is trained far
        // heavier — so tonnage and sets tell opposite stories.
        for ($week = 3; $week >= 0; $week--) {
            $date = Carbon::now()->subWeeks($week)->setTime(18, 0);
            $this->seedWorkout($user, $date, ['bench'], setsPerExercise: 6, weightKg: 60.0);
            $this->seedWorkout($user, $date->copy()->addHours(2), ['row'], setsPerExercise: 6, weightKg: 200.0);
        }

        $balance = (new MuscleBalance($user, new FilterCriteria(
            from: Carbon::now()->subDays(28)->startOfDay(),
            to: Carbon::now()->endOfDay(),
        )))->ratios();

        // Tonnage-based this was 0.3 and permanently "imbalanced"; set-based it
        // is 1.0, which is the truth.
        $this->assertTrue($balance['push_pull']['balanced']);
        $this->assertEqualsWithDelta(1.0, $balance['push_pull']['ratio'], 0.01);
    }

    public function test_muscle_balance_reports_no_data_rather_than_a_false_alarm(): void
    {
        $user = $this->makeAthlete();
        $this->seedExerciseTemplates($user, ['bench' => ['Bench Press', 'chest', []]]);
        $this->seedWorkout($user, Carbon::now()->subDays(2)->setTime(18, 0), ['bench'], setsPerExercise: 1);

        $balance = (new MuscleBalance($user, new FilterCriteria(
            from: Carbon::now()->subDays(28)->startOfDay(),
            to: Carbon::now()->endOfDay(),
        )))->ratios();

        $this->assertFalse($balance['push_pull']['has_data']);
        $this->assertNull($balance['push_pull']['ratio']);
    }

    public function test_projection_refuses_to_forecast_from_two_points(): void
    {
        $result = (new ProjectionService)->project([
            ['label' => '2026-01-01', 'value' => 100],
            ['label' => '2026-02-01', 'value' => 110],
        ]);

        // Previously returned r2 = 1.00 "strong" with a +-0.00 confidence band.
        $this->assertFalse($result['available']);
        $this->assertStringContainsString('data points', $result['reason']);
    }

    public function test_projection_does_not_claim_strength_on_a_small_sample(): void
    {
        $series = [];
        foreach (range(0, 4) as $i) {
            $series[] = ['label' => Carbon::parse('2026-01-01')->addWeeks($i)->toDateString(), 'value' => 100 + $i * 5];
        }

        $result = (new ProjectionService)->project($series);

        $this->assertTrue($result['available']);
        $this->assertSame(1.0, $result['r2']);
        $this->assertSame('weak', $result['quality']);
    }

    public function test_projection_refuses_a_steep_line_through_a_few_consecutive_days(): void
    {
        // Four daily weigh-ins during a water-weight swing previously
        // extrapolated 80 kg to 186 kg over a year.
        $series = [];
        foreach (range(0, 3) as $i) {
            $series[] = [
                'label' => Carbon::parse('2026-01-01')->addDays($i)->toDateString(),
                'value' => 80 + $i * 0.6,
            ];
        }

        $result = (new ProjectionService)->project($series);

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('days', $result['reason']);
    }

    public function test_projection_handles_quarter_and_semester_bucket_labels(): void
    {
        // These labels are not parseable by Carbon and previously threw.
        $result = (new ProjectionService)->project([
            ['label' => '2025-Q1', 'value' => 100],
            ['label' => '2025-Q2', 'value' => 110],
            ['label' => '2025-Q3', 'value' => 120],
            ['label' => '2025-Q4', 'value' => 130],
            ['label' => '2026-Q1', 'value' => 140],
        ]);

        $this->assertTrue($result['available']);
        $this->assertGreaterThan(0, $result['slope_per_week']);
    }

    public function test_female_athlete_is_not_scored_with_the_mens_body_fat_formula(): void
    {
        $user = $this->makeAthlete(['sex' => 'female', 'height_cm' => 165.0]);
        $user->body_fat_source = 'navy';
        $user->save();

        $user->bodyMeasurements()->create([
            'date' => Carbon::now()->toDateString(),
            'weight_kg' => 62.0,
            'neck_cm' => 32.0,
            'waist' => 76.0,
            'abdomen' => 76.0,
            'hips' => 98.0,
        ]);

        $status = (new BodyCompAnalytics($user))->status();

        // Men's equation on this athlete reads ~17%; the women's ~29%.
        $this->assertNotNull($status['navy_fat_percent']);
        $this->assertGreaterThan(25.0, $status['navy_fat_percent']);
    }

    public function test_trend_weight_agrees_with_a_steady_scale(): void
    {
        $user = $this->makeAthlete();
        $this->seedWeightTrend($user, 80.0, 0.0, 5);

        $this->assertEqualsWithDelta(80.0, (new BodyCompAnalytics($user))->trendWeightKg(), 0.01);
    }

    public function test_trend_weight_absorbs_a_single_spike_instead_of_jumping_to_it(): void
    {
        $user = $this->makeAthlete();
        $this->seedWeightTrend($user, 80.0, 0.0, 4, endingOn: Carbon::now()->subWeek());
        $user->bodyMeasurements()->create([
            'date' => Carbon::now()->toDateString(),
            'weight_kg' => 84.0,
        ]);

        $trend = (new BodyCompAnalytics($user))->trendWeightKg();

        // Pulled toward the new reading, but nowhere near swallowing it whole:
        // that gap is the whole point of showing a trend instead of a weigh-in.
        $this->assertGreaterThan(80.3, $trend);
        $this->assertLessThan(82.5, $trend);

        $this->assertEqualsWithDelta($trend, (new BodyCompAnalytics($user))->status()['trend_weight_kg'], 0.01);
    }

    public function test_waist_to_hip_risk_uses_who_sex_specific_cutoffs(): void
    {
        // 0.86 sits between the WHO cut-offs: risk for a woman (0.85), not
        // yet for a man (0.90).
        foreach (['male' => false, 'female' => true] as $sex => $expected) {
            $user = $this->makeAthlete(['sex' => $sex]);
            $user->bodyMeasurements()->create([
                'date' => Carbon::now()->toDateString(),
                'waist' => 86.0,
                'hips' => 100.0,
            ]);

            $status = (new BodyCompAnalytics($user))->status();

            $this->assertEqualsWithDelta(0.86, $status['waist_to_hip'], 0.001);
            $this->assertSame($expected, $status['waist_to_hip_risk'], $sex);
        }
    }

    public function test_waist_to_hip_risk_is_withheld_without_a_stated_sex(): void
    {
        $user = $this->makeAthlete(['sex' => null]);
        $user->bodyMeasurements()->create([
            'date' => Carbon::now()->toDateString(),
            'waist' => 95.0,
            'hips' => 100.0,
        ]);

        $status = (new BodyCompAnalytics($user))->status();

        // The number is shown; the judgement is not — the cut-offs are
        // sex-specific and guessing would colour it wrong for half of users.
        $this->assertNotNull($status['waist_to_hip']);
        $this->assertNull($status['waist_to_hip_risk']);
    }

    public function test_status_carries_rfm_when_waist_and_height_are_known(): void
    {
        $user = $this->makeAthlete(['height_cm' => 180.0]);
        $user->bodyMeasurements()->create([
            'date' => Carbon::now()->toDateString(),
            'waist' => 90.0,
        ]);

        $this->assertEqualsWithDelta(24.0, (new BodyCompAnalytics($user))->status()['rfm_percent'], 0.01);
    }
}
