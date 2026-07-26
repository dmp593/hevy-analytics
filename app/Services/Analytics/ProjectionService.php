<?php

namespace App\Services\Analytics;

use App\Science\Stats\LinearRegression;
use Illuminate\Support\Carbon;

/**
 * Trend-based projections for month/quarter/semester/year using OLS regression.
 * Accepts any labelled time series of {label: date-ish, value: float}.
 */
class ProjectionService
{
    /**
     * @param  array<int, array{label:string, value:float}>  $series
     * @param  bool  $dampen  apply logarithmic dampening (for near-ceiling metrics)
     */
    public function project(array $series, bool $dampen = false): array
    {
        $points = array_values(array_filter($series, fn ($p) => isset($p['value']) && is_numeric($p['value'])));
        if (count($points) < 2) {
            return ['available' => false, 'reason' => 'Not enough data points to project.'];
        }

        $base = Carbon::parse($points[0]['label']);
        $x = [];
        $y = [];
        foreach ($points as $p) {
            $x[] = (float) $base->diffInDays(Carbon::parse($p['label']));
            $y[] = (float) $p['value'];
        }

        $reg = LinearRegression::fit($x, $y);
        $lastX = end($x);
        $current = end($y);

        $horizons = [];
        foreach (PeriodService::projectionHorizons() as $name => $days) {
            $futureX = $lastX + $days;
            $value = $reg->predict($futureX);

            if ($dampen && $reg->slope != 0.0) {
                // Dampen gains as they extend: scale added delta by log falloff.
                $delta = $value - $current;
                $factor = 1 / (1 + log(1 + $days / 30));
                $value = $current + $delta * $factor;
            }

            $horizons[$name] = [
                'label' => PeriodService::label($name),
                'days' => $days,
                'value' => round($value, 2),
                'delta' => round($value - $current, 2),
                'confidence' => round($reg->confidenceHalfWidth(), 2),
            ];
        }

        return [
            'available' => true,
            'current' => round($current, 2),
            'slope_per_week' => round($reg->slope * 7, 3),
            'r2' => round($reg->r2, 3),
            'quality' => $this->quality($reg->r2),
            'horizons' => $horizons,
            'dampened' => $dampen,
        ];
    }

    private function quality(float $r2): string
    {
        return match (true) {
            $r2 >= 0.75 => 'strong',
            $r2 >= 0.4 => 'moderate',
            default => 'weak',
        };
    }
}
