<?php

namespace Tests\Unit;

use App\Services\Analytics\StrengthVerdict;
use Tests\TestCase;

/**
 * The performance page asks "is the weight on the bar actually going up" and
 * used to answer with tonnage, sets and reps — volume, which rises whenever you
 * add sets, whether or not a single lift improved. These tests pin the real
 * answer: per-lift e1RM direction.
 */
class StrengthVerdictTest extends TestCase
{
    private function trend(string $direction, float $perWeek, bool $reliable = true, int $sessions = 10): array
    {
        return [
            'direction' => $direction,
            'per_week' => $perWeek,
            'sessions' => $sessions,
            'r2' => $reliable ? 0.8 : 0.1,
            'reliable' => $reliable,
        ];
    }

    private function prs(array $keys): array
    {
        return array_map(fn ($k) => ['exercise' => ucfirst($k), 'template_id' => $k], $keys);
    }

    public function test_every_lift_rising_reads_as_good(): void
    {
        $v = (new StrengthVerdict(
            ['squat' => $this->trend('up', 0.3), 'bench' => $this->trend('up', 0.1)],
            $this->prs(['squat', 'bench']),
        ))->verdict();

        $this->assertSame('good', $v['tone']);
        $this->assertSame(2, $v['rising']);
        // Named mover is the fastest riser, not just the first key.
        $this->assertStringContainsString('Squat', $v['body']);
    }

    public function test_a_declining_lift_is_reported_ahead_of_good_news(): void
    {
        $v = (new StrengthVerdict(
            ['squat' => $this->trend('up', 0.4), 'bench' => $this->trend('down', -0.5)],
            $this->prs(['squat', 'bench']),
        ))->verdict();

        $this->assertSame('bad', $v['tone']);
        $this->assertSame(1, $v['falling']);
        $this->assertStringContainsString('Bench', $v['body']);
    }

    public function test_all_flat_reads_as_a_plateau(): void
    {
        $v = (new StrengthVerdict(
            ['squat' => $this->trend('flat', 0.0), 'bench' => $this->trend('flat', 0.01)],
            $this->prs(['squat', 'bench']),
        ))->verdict();

        $this->assertSame('warn', $v['tone']);
        $this->assertSame(2, $v['stalled']);
    }

    /**
     * A lift with a scattered trend is unknown, not stalled. Counting it as a
     * stall would invent bad news out of missing information.
     */
    public function test_unreliable_trends_are_excluded_rather_than_counted_as_stalled(): void
    {
        $v = (new StrengthVerdict(
            ['squat' => $this->trend('up', 0.3), 'curl' => $this->trend('flat', 0.0, reliable: false)],
            $this->prs(['squat', 'curl']),
        ))->verdict();

        $this->assertSame(0, $v['stalled']);
        $this->assertSame(1, $v['unknown']);
        $this->assertSame(1, $v['rising']);
        $this->assertSame('good', $v['tone']);
    }

    public function test_no_reliable_trend_at_all_produces_no_verdict(): void
    {
        $this->assertNull((new StrengthVerdict(
            ['squat' => $this->trend('up', 0.3, reliable: false)],
            $this->prs(['squat']),
        ))->verdict());

        $this->assertNull((new StrengthVerdict([], []))->verdict());
    }

    public function test_movers_are_ordered_by_size_of_change_regardless_of_direction(): void
    {
        $v = (new StrengthVerdict([
            'a' => $this->trend('up', 0.1),
            'b' => $this->trend('down', -0.9),
            'c' => $this->trend('up', 0.4),
        ], $this->prs(['a', 'b', 'c'])))->verdict();

        $this->assertSame([-0.9, 0.4, 0.1], array_column($v['movers'], 'per_week'));
    }

    public function test_weekly_slope_is_restated_per_year(): void
    {
        $v = (new StrengthVerdict(
            ['squat' => $this->trend('up', 0.25)],
            $this->prs(['squat']),
        ))->verdict();

        // 0.25 kg/week is 13 kg/year.
        $this->assertStringContainsString('+13.0 kg', $v['body']);
    }

    public function test_mixed_results_report_how_many_stalled(): void
    {
        $v = (new StrengthVerdict([
            'a' => $this->trend('up', 0.3),
            'b' => $this->trend('flat', 0.0),
            'c' => $this->trend('flat', 0.0),
        ], $this->prs(['a', 'b', 'c'])))->verdict();

        $this->assertSame(1, $v['rising']);
        $this->assertSame(2, $v['stalled']);
        $this->assertSame('warn', $v['tone']);
        $this->assertStringContainsString('2', $v['body']);
    }

    public function test_no_branch_leaves_an_unreplaced_placeholder(): void
    {
        $cases = [
            ['a' => $this->trend('up', 0.3)],
            ['a' => $this->trend('down', -0.3)],
            ['a' => $this->trend('flat', 0.0)],
            ['a' => $this->trend('up', 0.3), 'b' => $this->trend('flat', 0.0)],
            ['a' => $this->trend('up', 0.3), 'b' => $this->trend('down', -0.4)],
        ];

        foreach ($cases as $i => $trends) {
            $v = (new StrengthVerdict($trends, $this->prs(array_keys($trends))))->verdict();

            $this->assertDoesNotMatchRegularExpression('/:[a-z_]+/', $v['headline'], "case {$i} headline");
            $this->assertDoesNotMatchRegularExpression('/:[a-z_]+/', $v['body'], "case {$i} body");
        }
    }
}
