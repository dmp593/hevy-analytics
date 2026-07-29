<?php

namespace Tests\Unit;

use App\Services\Analytics\MuscleVerdict;
use Tests\TestCase;

/**
 * The muscle page's whole purpose is to say which muscles need more sets and how
 * many. It used to answer that with ten bars and four abbreviations per bar,
 * leaving the subtraction to the reader. These tests pin the subtraction.
 */
class MuscleVerdictTest extends TestCase
{
    private function row(string $muscle, float $perWeek, float $mev, float $mrv = 22): array
    {
        return [
            'muscle' => $muscle,
            'per_week' => $perWeek,
            'total_sets' => $perWeek * 10,
            'status' => 'x',
            'landmarks' => ['mv' => $mev - 2, 'mev' => $mev, 'mav' => $mev + 6, 'mrv' => $mrv],
        ];
    }

    public function test_a_muscle_below_mev_is_reported_with_the_sets_needed_to_reach_it(): void
    {
        $v = (new MuscleVerdict([$this->row('chest', 5.4, 10)]))->verdict();

        $this->assertSame(5.0, $v['gaps'][0]['need']);
        $this->assertStringContainsString('5', $v['body']);
    }

    /** A partial set cannot be performed, and rounding down leaves the gap open. */
    public function test_the_shortfall_rounds_up(): void
    {
        $v = (new MuscleVerdict([$this->row('chest', 7.1, 10)]))->verdict();

        $this->assertSame(3.0, $v['gaps'][0]['need']);
    }

    public function test_gaps_are_ordered_largest_first_so_the_biggest_win_leads(): void
    {
        $v = (new MuscleVerdict([
            $this->row('triceps', 8.0, 10),
            $this->row('lower_back', 1.0, 8),
            $this->row('chest', 5.0, 10),
        ]))->verdict();

        $this->assertSame([7.0, 5.0, 2.0], array_column($v['gaps'], 'need'));
    }

    public function test_more_than_three_shortfalls_names_three_and_counts_the_rest(): void
    {
        $v = (new MuscleVerdict([
            $this->row('chest', 1, 10),
            $this->row('lats', 2, 10),
            $this->row('quads', 3, 10),
            $this->row('calves', 4, 10),
            $this->row('biceps', 5, 10),
        ]))->verdict();

        $this->assertCount(5, $v['gaps']);
        // Three named inline, the remaining two summarised rather than listed.
        $this->assertSame(3, substr_count($v['body'], 'more sets a week'));
        $this->assertStringContainsString('2 other muscles', $v['body']);
    }

    public function test_everything_in_range_is_reported_as_good_not_as_a_problem(): void
    {
        $v = (new MuscleVerdict([
            $this->row('chest', 12, 10),
            $this->row('lats', 14, 10),
        ]))->verdict();

        $this->assertSame('good', $v['tone']);
        $this->assertSame([], $v['gaps']);
    }

    public function test_training_above_mrv_is_flagged_as_wasted_volume(): void
    {
        $v = (new MuscleVerdict([$this->row('chest', 26, 10, 22)]))->verdict();

        $this->assertSame('warn', $v['tone']);
        $this->assertSame(['Chest'], $v['excess']);
    }

    /** Most muscles short is a worse situation than one, and should read that way. */
    public function test_tone_escalates_when_most_muscles_are_short(): void
    {
        $few = (new MuscleVerdict([
            $this->row('chest', 5, 10),
            $this->row('lats', 12, 10),
            $this->row('quads', 12, 10),
        ]))->verdict();

        $many = (new MuscleVerdict([
            $this->row('chest', 5, 10),
            $this->row('lats', 5, 10),
            $this->row('quads', 12, 10),
        ]))->verdict();

        $this->assertSame('warn', $few['tone']);
        $this->assertSame('bad', $many['tone']);
    }

    /**
     * A ratio of 2.0 and one of 0.5 are the same imbalance seen from either end.
     * Measuring distance linearly would rank the under-trained direction as
     * milder and quietly under-report exactly the case worth reporting.
     */
    public function test_imbalance_treats_both_directions_as_equally_severe(): void
    {
        $sets = [$this->row('chest', 12, 10)];

        $heavyPush = (new MuscleVerdict($sets, ['push_pull' => ['ratio' => 2.0, 'label_a' => 'push', 'label_b' => 'pull']]))->verdict();
        $heavyPull = (new MuscleVerdict($sets, ['push_pull' => ['ratio' => 0.5, 'label_a' => 'push', 'label_b' => 'pull']]))->verdict();

        $this->assertNotNull($heavyPush['imbalance']);
        $this->assertNotNull($heavyPull['imbalance']);
        $this->assertStringContainsString('2.0', $heavyPush['imbalance']);
        $this->assertStringContainsString('2.0', $heavyPull['imbalance']);
        // ...and they name opposite sides as the dominant one.
        $this->assertNotSame($heavyPush['imbalance'], $heavyPull['imbalance']);
    }

    public function test_a_balanced_ratio_raises_no_imbalance(): void
    {
        $v = (new MuscleVerdict(
            [$this->row('chest', 12, 10)],
            ['push_pull' => ['ratio' => 1.1, 'label_a' => 'push', 'label_b' => 'pull']],
        ))->verdict();

        $this->assertNull($v['imbalance']);
    }

    public function test_only_the_worst_imbalance_is_reported(): void
    {
        $v = (new MuscleVerdict([$this->row('chest', 12, 10)], [
            'push_pull' => ['ratio' => 1.6, 'label_a' => 'push', 'label_b' => 'pull'],
            'upper_lower' => ['ratio' => 3.2, 'label_a' => 'upper', 'label_b' => 'lower'],
        ]))->verdict();

        // 3.2 is further from balanced than 1.6, so upper/lower is the one named.
        $this->assertStringContainsString(__('app.balance_groups.upper'), $v['imbalance']);
        $this->assertStringContainsString('3.2', $v['imbalance']);
    }

    public function test_an_indeterminate_ratio_is_skipped_rather_than_treated_as_zero(): void
    {
        $v = (new MuscleVerdict([$this->row('chest', 12, 10)], [
            'push_pull' => ['ratio' => null, 'verdict' => 'not_enough'],
        ]))->verdict();

        $this->assertNull($v['imbalance']);
    }

    public function test_no_sets_produces_no_verdict(): void
    {
        $this->assertNull((new MuscleVerdict([]))->verdict());
    }

    public function test_no_branch_leaves_an_unreplaced_placeholder(): void
    {
        foreach ([
            [$this->row('chest', 5, 10)],
            [$this->row('chest', 26, 10, 22)],
            [$this->row('chest', 12, 10)],
        ] as $i => $sets) {
            $v = (new MuscleVerdict($sets, ['push_pull' => ['ratio' => 2.0, 'label_a' => 'push', 'label_b' => 'pull']]))->verdict();

            $this->assertDoesNotMatchRegularExpression('/:[a-z_]+/', $v['headline'], "case {$i} headline");
            $this->assertDoesNotMatchRegularExpression('/:[a-z_]+/', $v['body'], "case {$i} body");
            $this->assertDoesNotMatchRegularExpression('/:[a-z_]+/', (string) $v['imbalance'], "case {$i} imbalance");
        }
    }
}
