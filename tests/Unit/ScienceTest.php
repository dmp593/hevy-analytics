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

    // --- Regression: e1RM must not blow up outside its validated rep range ---

    public function test_e1rm_is_clamped_outside_the_formulas_usable_range(): void
    {
        // Brzycki's denominator (37 - reps) approaches zero: unclamped this
        // returned ~1910 kg from a 100 kg set.
        $absurd = OneRepMax::estimate(100, 36);
        $this->assertNotNull($absurd);
        $this->assertLessThan(200.0, $absurd);
        $this->assertSame(OneRepMax::estimate(100, OneRepMax::MAX_FORMULA_REPS), $absurd);
    }

    public function test_clamping_does_not_erase_the_rpe_signal(): void
    {
        // The numerical guard sits above the reliability threshold, so a set
        // taken to failure and one left well short still differ.
        $toFailure = OneRepMax::estimate(100, 12, 10.0);
        $wellShort = OneRepMax::estimate(100, 12, 8.0);

        $this->assertNotSame($toFailure, $wellShort);
        $this->assertGreaterThan($toFailure, $wellShort);
    }

    public function test_e1rm_never_decreases_as_reps_increase(): void
    {
        $previous = 0.0;
        foreach (range(1, 40) as $reps) {
            $value = OneRepMax::estimate(100, (float) $reps);
            $this->assertNotNull($value);
            $this->assertGreaterThanOrEqual($previous, $value, "e1RM fell going into {$reps} reps");
            $previous = $value;
        }
    }

    public function test_rpe_inflated_estimate_is_not_treated_as_a_reliable_pr(): void
    {
        // 12 reps at RPE 6 implies 4 reps in reserve — an inference, not a lift.
        $this->assertFalse(OneRepMax::isReliableSet(12, 6.0));
        $this->assertTrue(OneRepMax::isReliableSet(8, 9.0));
        $this->assertTrue(OneRepMax::isReliableSet(8, null));
        $this->assertFalse(OneRepMax::isReliableSet(20, 10.0));
    }

    // --- Regression: women must not be scored with the men's Navy equation ---

    public function test_navy_body_fat_uses_the_female_equation_for_women(): void
    {
        // Reference figures: 1.65 m woman, neck 32, waist 76, hip 98.
        $female = BodyComposition::navyBodyFatWomen(32, 76, 98, 165);
        $this->assertNotNull($female);
        $this->assertEqualsWithDelta(29.4, $female, 0.2);

        // The men's equation on the same person reads far too low, which is
        // exactly the bug: it silently under-reported women by ~12 points.
        $male = BodyComposition::navyBodyFatMen(32, 76, 165);
        $this->assertGreaterThan($male + 8, $female);
    }

    public function test_navy_dispatcher_routes_by_sex(): void
    {
        $this->assertSame(
            BodyComposition::navyBodyFatWomen(32, 76, 98, 165),
            BodyComposition::navyBodyFat('female', 32, 76, 165, 98),
        );

        $this->assertSame(
            BodyComposition::navyBodyFatMen(39, 84, 178),
            BodyComposition::navyBodyFat('male', 39, 84, 178),
        );
    }

    public function test_navy_returns_null_for_women_without_a_hip_measurement(): void
    {
        // Better to ask for the measurement than to quietly use the men's formula.
        $this->assertNull(BodyComposition::navyBodyFat('female', 32, 76, 165));
    }

    public function test_relative_fat_mass_matches_the_published_equation(): void
    {
        // 180 cm, 90 cm waist: 64 - 20*(180/90) = 24.0 for a man; women +12.
        $this->assertSame(24.0, BodyComposition::relativeFatMass(180, 90, 'male'));
        $this->assertSame(36.0, BodyComposition::relativeFatMass(180, 90, 'female'));
    }

    public function test_relative_fat_mass_rejects_impossible_tape_values(): void
    {
        $this->assertNull(BodyComposition::relativeFatMass(0, 90, 'male'));
        $this->assertNull(BodyComposition::relativeFatMass(180, 0, 'male'));

        // A 40 cm "waist" on a 180 cm frame is a typo, not a body.
        $this->assertNull(BodyComposition::relativeFatMass(180, 40, 'male'));
    }
}
