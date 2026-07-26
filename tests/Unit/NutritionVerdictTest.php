<?php

namespace Tests\Unit;

use App\Models\NutritionTarget;
use App\Services\Analytics\NutritionVerdict;
use Tests\TestCase;

/**
 * The adaptive-maintenance measurement is the strongest claim this app has: it
 * derives what an athlete's maintenance actually is from their own intake and
 * weight trend, which no calorie formula can do. These tests make sure it is
 * stated only when it has been measured, and never dressed up when it has not.
 */
class NutritionVerdictTest extends TestCase
{
    private function target(?float $adaptive, ?float $formula = 2800): NutritionTarget
    {
        return new NutritionTarget([
            'basis' => [
                'adaptive_maintenance' => $adaptive,
                'formula_tdee' => $formula,
            ],
        ]);
    }

    public function test_without_enough_logged_days_it_says_the_targets_are_an_estimate(): void
    {
        $v = (new NutritionVerdict($this->target(null), loggedDays: 2))->verdict();

        $this->assertSame(__('app.nutrition.verdict.estimate_headline'), $v['headline']);
        $this->assertNull($v['measured']);
        // It must name what is missing and how much, not just say "not enough".
        $this->assertStringContainsString('5', $v['body']);
    }

    public function test_a_measurement_close_to_the_formula_is_reported_as_agreement(): void
    {
        $v = (new NutritionVerdict($this->target(2850, 2800), loggedDays: 28))->verdict();

        $this->assertSame(__('app.nutrition.verdict.agrees_headline'), $v['headline']);
        $this->assertSame(50.0, (float) $v['gap']);
    }

    public function test_burning_more_than_predicted_is_named_and_quantified(): void
    {
        $v = (new NutritionVerdict($this->target(3100, 2800), loggedDays: 28))->verdict();

        $this->assertSame(__('app.nutrition.verdict.higher_headline'), $v['headline']);
        $this->assertSame(300.0, (float) $v['gap']);
        $this->assertStringContainsString('300', $v['body']);
        $this->assertStringContainsString('3,100', $v['body']);
    }

    public function test_burning_less_than_predicted_is_named_and_quantified(): void
    {
        $v = (new NutritionVerdict($this->target(2450, 2800), loggedDays: 28))->verdict();

        $this->assertSame(__('app.nutrition.verdict.lower_headline'), $v['headline']);
        $this->assertSame(-350.0, (float) $v['gap']);
        $this->assertStringContainsString('350', $v['body']);
    }

    /**
     * Food logging and scale readings are both noisy. A gap of tens of calories
     * is not a finding, and presenting it as one invites the athlete to chase it.
     */
    public function test_a_gap_inside_logging_noise_is_treated_as_agreement(): void
    {
        foreach ([2800 + 99, 2800 - 99, 2800] as $measured) {
            $v = (new NutritionVerdict($this->target($measured, 2800), loggedDays: 28))->verdict();

            $this->assertSame(
                __('app.nutrition.verdict.agrees_headline'),
                $v['headline'],
                "measured {$measured} should read as agreement",
            );
        }
    }

    public function test_a_measurement_without_a_formula_to_compare_still_reports_the_measurement(): void
    {
        $v = (new NutritionVerdict($this->target(2900, null), loggedDays: 28))->verdict();

        $this->assertNotNull($v);
        $this->assertSame(2900.0, $v['measured']);
        $this->assertNull($v['gap']);
        $this->assertDoesNotMatchRegularExpression('/:[a-z_]+/', $v['body']);
    }

    public function test_no_target_produces_no_verdict(): void
    {
        $this->assertNull((new NutritionVerdict(null, loggedDays: 30))->verdict());
    }

    public function test_no_branch_leaves_an_unreplaced_placeholder(): void
    {
        $cases = [
            [null, 2800, 0],
            [null, 2800, 6],
            [2850, 2800, 28],
            [3100, 2800, 28],
            [2450, 2800, 28],
            [2900, null, 28],
        ];

        foreach ($cases as $i => [$adaptive, $formula, $days]) {
            $v = (new NutritionVerdict($this->target($adaptive, $formula), loggedDays: $days))->verdict();

            $this->assertDoesNotMatchRegularExpression('/:[a-z_]+/', $v['body'], "case {$i}: {$v['body']}");
            $this->assertDoesNotMatchRegularExpression('/:[a-z_]+/', $v['headline'], "case {$i}");
        }
    }
}
