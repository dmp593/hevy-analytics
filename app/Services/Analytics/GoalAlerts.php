<?php

namespace App\Services\Analytics;

use App\Models\User;

/**
 * Goal-aware alerting. Compares observed body-composition trends to the
 * active goal's target rate and flags fat-gain / muscle-loss risks.
 */
class GoalAlerts
{
    public function __construct(private readonly User $user) {}

    /** @return array<int, array{level:string, title:string, message:string}> */
    public function all(): array
    {
        $alerts = [];
        $goal = $this->user->activeGoal();
        $profile = $goal?->profile();
        $targetRate = $profile?->target_rate_pct_bw_per_week ?? 0.35;

        $bc = new BodyCompAnalytics($this->user);
        $rate = $bc->weightRateKgPerWeek();
        $part = $bc->partitioning();

        if ($rate && $rate['pct_bw_per_week'] !== null) {
            $observed = $rate['pct_bw_per_week'];
            $isBulk = $targetRate > 0.05;
            $isCut = $targetRate < -0.05;

            if ($isBulk) {
                if ($observed > $targetRate * 1.6) {
                    $alerts[] = $this->alert('warning', 'Gaining too fast',
                        sprintf('Weight is rising %.2f%%BW/week vs target %.2f%%. Excess likely fat — trim ~150-250 kcal.', $observed, $targetRate));
                } elseif ($observed < $targetRate * 0.3) {
                    $alerts[] = $this->alert('info', 'Bulk stalling',
                        sprintf('Only %.2f%%BW/week gained (target %.2f%%). Add ~150-250 kcal to keep building.', $observed, $targetRate));
                } else {
                    $alerts[] = $this->alert('success', 'On track',
                        sprintf('Gaining %.2f%%BW/week — right in the lean-bulk band.', $observed));
                }
            } elseif ($isCut) {
                if ($observed > $targetRate * 0.3) {
                    $alerts[] = $this->alert('warning', 'Cut too slow / gaining',
                        sprintf('Losing only %.2f%%BW/week (target %.2f%%). Reduce ~200 kcal.', $observed, $targetRate));
                } elseif ($observed < $targetRate * 1.6) {
                    $alerts[] = $this->alert('warning', 'Cutting too aggressively',
                        sprintf('Dropping %.2f%%BW/week (target %.2f%%) — muscle-loss risk. Add calories.', $observed, $targetRate));
                } else {
                    $alerts[] = $this->alert('success', 'On track',
                        sprintf('Losing %.2f%%BW/week — sustainable cut pace.', $observed));
                }
            }
        }

        if ($part && $part['p_ratio'] !== null && $targetRate > 0.05 && $part['p_ratio'] < 0.5) {
            $caveat = ($part['source'] ?? 'scale') === 'scale'
                ? ' Note: body-fat comes from a BIA scale (noisy) — confirm with the mirror, waist tape and strength.'
                : '';

            if ($part['reliable']) {
                $alerts[] = $this->alert('warning', 'Poor gain partitioning',
                    sprintf('Estimated ~%d%% of recent weight gain was lean mass, based on a trend over %d readings.%s',
                        (int) round($part['p_ratio'] * 100), $part['fat_points'], $caveat));
            } else {
                $alerts[] = $this->alert('info', 'Partitioning estimate (low confidence)',
                    sprintf('Rough estimate suggests ~%d%% of gain was lean, but there isn\'t enough consistent data to trust it yet.%s',
                        (int) round($part['p_ratio'] * 100), $caveat));
            }
        }

        if ($part && $part['reliable'] && $part['delta_fat_pct'] !== null && $part['delta_fat_pct'] > 1.5) {
            $alerts[] = $this->alert('warning', 'Body-fat climbing',
                sprintf('Body fat trending up ~%.1f points recently. Watch the surplus size.', $part['delta_fat_pct']));
        }

        $status = $bc->status();
        if ($status['waist_to_height'] !== null && $status['waist_to_height'] > 0.5) {
            $alerts[] = $this->alert('info', 'Waist-to-height above 0.5',
                'Waist-to-height ratio exceeds the 0.5 health guideline — monitor waist during the bulk.');
        }

        foreach ($status['symmetry'] as $s) {
            if ($s['diff_pct'] > 5) {
                $alerts[] = $this->alert('info', ucfirst($s['part']).' asymmetry',
                    sprintf('%s differs %.1f%% left vs right — consider unilateral work.', ucfirst($s['part']), $s['diff_pct']));
            }
        }

        if (empty($alerts)) {
            $alerts[] = $this->alert('info', 'Not enough data yet',
                'Log a few more body measurements over time to unlock trend-based alerts.');
        }

        return $alerts;
    }

    private function alert(string $level, string $title, string $message): array
    {
        return compact('level', 'title', 'message');
    }
}
