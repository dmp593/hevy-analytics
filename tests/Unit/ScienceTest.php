<?php

namespace Tests\Unit;

use App\Science\BodyComp\BodyComposition;
use App\Science\Goals\GoalProfile;
use App\Science\Nutrition\Bmr;
use App\Science\Nutrition\Macros;
use App\Science\Stats\LinearRegression;
use App\Science\Strength\OneRepMax;
use App\Science\Strength\StrengthScore;
use App\Science\Strength\StrengthStandards;
use App\Science\Volume\MuscleLandmarks;
use PHPUnit\Framework\TestCase;

class ScienceTest extends TestCase
{
    public function test_epley_and_brzycki_agree_at_ten_reps(): void
    {
        // Wikipedia: 100 for 10 reps -> ~133 for both formulas.
        $this->assertEqualsWithDelta(133.33, OneRepMax::epley(100, 10), 0.1);
        $this->assertEqualsWithDelta(133.33, OneRepMax::brzycki(100, 10), 0.1);
        $this->assertEqualsWithDelta(133.33, OneRepMax::estimate(100, 10), 0.1);
    }

    public function test_e1rm_returns_weight_for_single_rep(): void
    {
        $this->assertSame(100.0, OneRepMax::estimate(100, 1));
    }

    public function test_e1rm_rir_adjustment_increases_estimate(): void
    {
        $atFailure = OneRepMax::estimate(100, 8, 10);   // 0 RIR
        $withReserve = OneRepMax::estimate(100, 8, 8);  // 2 RIR -> effective 10 reps
        $this->assertGreaterThan($atFailure, $withReserve);
    }

    public function test_e1rm_null_on_bad_input(): void
    {
        $this->assertNull(OneRepMax::estimate(null, 10));
        $this->assertNull(OneRepMax::estimate(100, 0));
    }

    public function test_load_for_reps_is_inverse_of_epley(): void
    {
        $e1rm = OneRepMax::epley(80, 5);
        $this->assertEqualsWithDelta(80.0, OneRepMax::loadForReps($e1rm, 5), 0.1);
    }

    public function test_mifflin_st_jeor(): void
    {
        // 80kg, 180cm, 30y male => 10*80+6.25*180-5*30+5 = 1780
        $this->assertEqualsWithDelta(1780, Bmr::mifflinStJeor(80, 180, 30, 'male'), 0.5);
        // female subtracts 161 vs +5 => 166 less
        $this->assertEqualsWithDelta(1614, Bmr::mifflinStJeor(80, 180, 30, 'female'), 0.5);
    }

    public function test_katch_mcardle(): void
    {
        // 370 + 21.6*60 = 1666
        $this->assertEqualsWithDelta(1666, Bmr::katchMcArdle(60), 0.5);
    }

    public function test_tdee_and_target_calories(): void
    {
        $tdee = Macros::tdee(1666, 1.55);
        $this->assertEqualsWithDelta(2582.3, $tdee, 0.5);
        $this->assertEqualsWithDelta(2841, Macros::targetCalories($tdee, 10), 1);
    }

    public function test_macro_split_hits_calorie_target(): void
    {
        $split = Macros::split(2600, 70, 2.0, 0.8);
        $this->assertSame(140.0, $split['protein_g']);
        $this->assertSame(56.0, $split['fat_g']);
        $total = $split['protein_kcal'] + $split['fat_kcal'] + $split['carb_kcal'];
        $this->assertEqualsWithDelta(2600, $total, 4);
    }

    public function test_weekly_weight_change_from_calorie_delta(): void
    {
        // +500 kcal/day * 7 / 7700 ≈ 0.455 kg/week
        $this->assertEqualsWithDelta(0.455, Macros::weeklyWeightChangeKg(500), 0.01);
    }

    public function test_ffmi_and_normalized(): void
    {
        // lean 60kg at 180cm => 60/1.8^2 = 18.52
        $this->assertEqualsWithDelta(18.52, BodyComposition::ffmi(60, 180), 0.05);
        // normalized adds 6.1*(1.8-1.8)=0 at 180cm
        $this->assertEqualsWithDelta(18.52, BodyComposition::ffmiNormalized(60, 180), 0.05);
    }

    public function test_lean_mass_from_fat(): void
    {
        $this->assertSame(80.0, BodyComposition::leanMassFromFat(100, 20));
    }

    public function test_navy_body_fat_men_reasonable(): void
    {
        $bf = BodyComposition::navyBodyFatMen(37, 84, 178);
        $this->assertGreaterThan(5, $bf);
        $this->assertLessThan(40, $bf);
    }

    public function test_symmetry_pct(): void
    {
        $this->assertSame(0.0, BodyComposition::symmetryPct(40, 40));
        $this->assertEqualsWithDelta(5.0, BodyComposition::symmetryPct(39, 41), 0.1);
    }

    public function test_linear_regression_perfect_fit(): void
    {
        $reg = LinearRegression::fit([0, 1, 2, 3], [1, 3, 5, 7]);
        $this->assertEqualsWithDelta(2.0, $reg->slope, 0.001);
        $this->assertEqualsWithDelta(1.0, $reg->intercept, 0.001);
        $this->assertEqualsWithDelta(1.0, $reg->r2, 0.001);
        $this->assertEqualsWithDelta(21.0, $reg->predict(10), 0.001);
    }

    public function test_muscle_landmarks_classification(): void
    {
        $this->assertSame('below_maintenance', MuscleLandmarks::classify('chest', 2));
        $this->assertSame('optimal', MuscleLandmarks::classify('chest', 14));
        $this->assertSame('junk', MuscleLandmarks::classify('chest', 30));
    }

    public function test_strength_scores_positive(): void
    {
        $this->assertGreaterThan(0, StrengthScore::wilks(100, 80, 'male'));
        $this->assertGreaterThan(0, StrengthScore::dots(100, 80, 'male'));
        $this->assertSame(1.25, StrengthScore::relative(100, 80));
    }

    public function test_goal_profile_preset_defaults_and_override(): void
    {
        $preset = GoalProfile::preset('cut');
        $this->assertLessThan(0, $preset->calorie_adjustment_pct);
        $this->assertGreaterThanOrEqual(2.3, $preset->protein_g_per_kg);
    }

    public function test_strength_standard_percentiles_match_levels(): void
    {
        // Bench 98kg @ 80kg male ~= Intermediate (50th percentile) per Strength Level.
        $int = StrengthStandards::evaluate('bench_press', 98, 80, 'male', 30);
        $this->assertEqualsWithDelta(50, $int['percentile'], 4);
        $this->assertSame('Intermediate', $int['level']);

        // Advanced standard (~132kg) lands in the Advanced band.
        $adv = StrengthStandards::evaluate('bench_press', 132, 80, 'male', 30);
        $this->assertSame('Advanced', $adv['level']);

        // A tiny lift is Beginner and low percentile.
        $beg = StrengthStandards::evaluate('bench_press', 40, 80, 'male', 30);
        $this->assertSame('Beginner', $beg['level']);
        $this->assertLessThan(20, $beg['percentile']);
    }

    public function test_strength_standard_age_adjustment_helps_older_lifters(): void
    {
        $young = StrengthStandards::evaluate('bench_press', 90, 80, 'male', 30)['percentile'];
        $old = StrengthStandards::evaluate('bench_press', 90, 80, 'male', 65)['percentile'];
        // Same lift ranks higher for an older lifter (compared to age peers).
        $this->assertGreaterThan($young, $old);
    }

    public function test_strength_standard_exercise_matcher(): void
    {
        $this->assertSame('bench_press', StrengthStandards::keyForTitle('Bench Press (Barbell)'));
        $this->assertSame('deadlift', StrengthStandards::keyForTitle('Deadlift (Barbell)'));
        $this->assertSame('romanian_deadlift', StrengthStandards::keyForTitle('Romanian Deadlift (Barbell)'));
        $this->assertSame('barbell_curl', StrengthStandards::keyForTitle('EZ Bar Biceps Curl'));
        // Dumbbell bench has no barbell standard.
        $this->assertNull(StrengthStandards::keyForTitle('Bench Press (Dumbbell)'));
    }
}
