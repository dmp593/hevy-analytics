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

    /**
     * Rates formatted once, so every alert reads the same and a translator
     * never has to reproduce a sprintf format string.
     *
     * @return array<string, string>
     */
    private function rates(float $observed, float $target): array
    {
        return [
            'observed' => number_format($observed, 2),
            'target' => number_format($target, 2),
        ];
    }

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
                    $alerts[] = $this->alert('warning', __('app.alerts.gaining_too_fast'),
                        __('app.alerts.gaining_too_fast_body', $this->rates($observed, $targetRate)));
                } elseif ($observed < $targetRate * 0.3) {
                    $alerts[] = $this->alert('info', __('app.alerts.bulk_stalling'),
                        __('app.alerts.bulk_stalling_body', $this->rates($observed, $targetRate)));
                } else {
                    $alerts[] = $this->alert('success', __('app.alerts.on_track'),
                        __('app.alerts.on_track_bulk_body', $this->rates($observed, $targetRate)));
                }
            } elseif ($isCut) {
                if ($observed > $targetRate * 0.3) {
                    $alerts[] = $this->alert('warning', __('app.alerts.cut_too_slow'),
                        __('app.alerts.cut_too_slow_body', $this->rates($observed, $targetRate)));
                } elseif ($observed < $targetRate * 1.6) {
                    $alerts[] = $this->alert('warning', __('app.alerts.cut_too_fast'),
                        __('app.alerts.cut_too_fast_body', $this->rates($observed, $targetRate)));
                } else {
                    $alerts[] = $this->alert('success', __('app.alerts.on_track'),
                        __('app.alerts.on_track_cut_body', $this->rates($observed, $targetRate)));
                }
            }
        }

        if ($part && $part['p_ratio'] !== null && $targetRate > 0.05 && $part['p_ratio'] < 0.5) {
            $caveat = ($part['source'] ?? 'scale') === 'scale'
                ? ' '.__('app.alerts.bia_caveat')
                : '';

            if ($part['reliable']) {
                $alerts[] = $this->alert('warning', __('app.alerts.poor_partitioning'),
                    __('app.alerts.poor_partitioning_body', [
                        'percent' => (int) round($part['p_ratio'] * 100),
                        'readings' => $part['fat_points'],
                    ]).$caveat);
            } else {
                $alerts[] = $this->alert('info', __('app.alerts.poor_partitioning_low'),
                    __('app.alerts.poor_partitioning_low_body', [
                        'percent' => (int) round($part['p_ratio'] * 100),
                    ]).$caveat);
            }
        }

        if ($part && $part['reliable'] && $part['delta_fat_pct'] !== null && $part['delta_fat_pct'] > 1.5) {
            $alerts[] = $this->alert('warning', __('app.alerts.fat_climbing'),
                __('app.alerts.fat_climbing_body', ['points' => number_format($part['delta_fat_pct'], 1)]));
        }

        $status = $bc->status();
        if ($status['waist_to_height'] !== null && $status['waist_to_height'] > 0.5) {
            $alerts[] = $this->alert('info', __('app.alerts.waist_high'), __('app.alerts.waist_high_body'));
        }

        foreach ($status['symmetry'] as $s) {
            if ($s['diff_pct'] > 5) {
                $part_name = __('app.muscles.'.$s['part']);
                $part_name = str_contains($part_name, 'app.muscles.') ? ucfirst($s['part']) : $part_name;

                $alerts[] = $this->alert('info', __('app.alerts.asymmetry', ['part' => $part_name]),
                    __('app.alerts.asymmetry_body', [
                        'part' => $part_name,
                        'percent' => number_format($s['diff_pct'], 1),
                    ]));
            }
        }

        if (empty($alerts)) {
            $alerts[] = $this->alert('info', __('app.alerts.no_data'), __('app.alerts.no_data_body'));
        }

        return $alerts;
    }

    private function alert(string $level, string $title, string $message): array
    {
        return compact('level', 'title', 'message');
    }
}
