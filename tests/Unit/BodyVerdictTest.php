<?php

namespace Tests\Unit;

use App\Services\Analytics\BodyVerdict;
use Tests\TestCase;

/**
 * The body page used to end with a fixed sentence claiming chest and arms were
 * rising while the waist stayed flat — printed for everyone, including athletes
 * whose waist was climbing fastest of all. These tests exist to make sure the
 * replacement says what the numbers say, especially when the news is bad.
 */
class BodyVerdictTest extends TestCase
{
    private function verdict(array $overrides = []): ?array
    {
        return (new BodyVerdict(array_replace([
            'weight' => ['kg_per_week' => 0.1],
            'waist' => ['per_month' => 0.0],
            'chest' => ['per_month' => 0.5],
            'bicep' => ['per_month' => 0.3],
            'lift' => ['exercise' => 'Squat (Barbell)', 'trend' => 'up'],
        ], $overrides)))->verdict();
    }

    public function test_size_and_strength_rising_with_a_flat_waist_reads_as_muscle_gain(): void
    {
        $v = $this->verdict();

        $this->assertSame('good', $v['tone']);
        $this->assertSame(__('app.body.verdict.gaining_muscle'), $v['headline']);
        $this->assertSame('high', $v['confidence']);
    }

    /** The exact case the old hardcoded sentence got wrong. */
    public function test_a_growing_waist_is_never_described_as_flat(): void
    {
        $v = $this->verdict(['waist' => ['per_month' => 0.9]]);

        $this->assertNotSame(__('app.body.verdict.gaining_muscle'), $v['headline']);
        $this->assertStringNotContainsString(__('app.body.verdict.waist_flat', ['value' => '']), $v['body']);
        $this->assertContains($v['tone'], ['warn', 'bad']);
    }

    public function test_a_waist_growing_alone_is_called_fat_gain(): void
    {
        $v = $this->verdict([
            'waist' => ['per_month' => 0.8],
            'chest' => ['per_month' => 0.0],
            'bicep' => ['per_month' => 0.0],
            'lift' => ['exercise' => 'Squat (Barbell)', 'trend' => 'down'],
        ]);

        $this->assertSame(__('app.body.verdict.gaining_fat'), $v['headline']);
        $this->assertSame('bad', $v['tone']);
    }

    public function test_growing_everywhere_including_the_waist_is_reported_as_a_mixed_gain(): void
    {
        $v = $this->verdict(['waist' => ['per_month' => 0.6]]);

        $this->assertSame(__('app.body.verdict.gaining_both'), $v['headline']);
        $this->assertSame('warn', $v['tone']);
    }

    public function test_falling_waist_with_held_size_reads_as_a_clean_cut(): void
    {
        $v = $this->verdict([
            'weight' => ['kg_per_week' => -0.3],
            'waist' => ['per_month' => -0.7],
        ]);

        $this->assertSame(__('app.body.verdict.losing_fat_keeping_muscle'), $v['headline']);
        $this->assertSame('good', $v['tone']);
    }

    public function test_losing_weight_while_losing_strength_raises_the_muscle_loss_warning(): void
    {
        $v = $this->verdict([
            'weight' => ['kg_per_week' => -0.6],
            'waist' => ['per_month' => 0.0],
            'chest' => ['per_month' => 0.0],
            'bicep' => ['per_month' => 0.0],
            'lift' => ['exercise' => 'Squat (Barbell)', 'trend' => 'down'],
        ]);

        $this->assertSame(__('app.body.verdict.losing_muscle_risk'), $v['headline']);
        $this->assertStringContainsString('Squat (Barbell)', $v['body']);
    }

    public function test_movement_inside_tape_noise_is_not_treated_as_a_trend(): void
    {
        $v = $this->verdict([
            'weight' => ['kg_per_week' => 0.01],
            'waist' => ['per_month' => 0.1],
            'chest' => ['per_month' => 0.15],
            'bicep' => ['per_month' => -0.1],
            'lift' => null,
        ]);

        $this->assertSame(__('app.body.verdict.holding'), $v['headline']);
    }

    public function test_no_measurements_produces_no_verdict_rather_than_a_guess(): void
    {
        $this->assertNull((new BodyVerdict([
            'weight' => null, 'waist' => null, 'chest' => null, 'bicep' => null, 'lift' => null,
        ]))->verdict());
    }

    public function test_confidence_falls_when_fewer_measurements_corroborate(): void
    {
        $one = $this->verdict([
            'weight' => null, 'waist' => null, 'bicep' => null, 'lift' => null,
            'chest' => ['per_month' => 0.5],
        ]);

        $this->assertSame('low', $one['confidence']);

        $two = $this->verdict([
            'weight' => null, 'bicep' => null, 'lift' => null,
            'chest' => ['per_month' => 0.5],
            'waist' => ['per_month' => 0.0],
        ]);

        $this->assertSame('moderate', $two['confidence']);
    }

    /**
     * A placeholder left unreplaced is a visible bug and looks like a typo to
     * the user, so no branch may leave one behind.
     */
    public function test_no_branch_leaves_an_unreplaced_placeholder(): void
    {
        $cases = [
            [],
            ['waist' => ['per_month' => 0.9]],
            ['waist' => ['per_month' => 0.8], 'chest' => ['per_month' => 0.0], 'bicep' => ['per_month' => 0.0], 'lift' => ['exercise' => 'X', 'trend' => 'down']],
            ['weight' => ['kg_per_week' => -0.3], 'waist' => ['per_month' => -0.7]],
            ['weight' => ['kg_per_week' => -0.6], 'chest' => ['per_month' => 0.0], 'bicep' => ['per_month' => 0.0], 'lift' => ['exercise' => 'X', 'trend' => 'down']],
            ['chest' => ['per_month' => 0.5], 'bicep' => ['per_month' => 0.0], 'lift' => null],
            ['weight' => ['kg_per_week' => 0.0], 'waist' => ['per_month' => 0.0], 'chest' => ['per_month' => 0.0], 'bicep' => ['per_month' => 0.0], 'lift' => null],
            ['waist' => null],
        ];

        foreach ($cases as $i => $case) {
            $v = $this->verdict($case);
            $this->assertNotNull($v, "case {$i} returned null");
            $this->assertDoesNotMatchRegularExpression('/:[a-z_]+/', $v['body'], "case {$i} left a placeholder: {$v['body']}");
            $this->assertNotSame('', trim($v['headline']), "case {$i} has an empty headline");
        }
    }
}
