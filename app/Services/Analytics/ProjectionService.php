<?php

namespace App\Services\Analytics;

use App\Science\Stats\LinearRegression;

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
    /** Below this many points a straight line through the data means nothing. */
    private const MIN_POINTS = 4;

    public function project(array $series, bool $dampen = false): array
    {
        $points = array_values(array_filter($series, fn ($p) => isset($p['value']) && is_numeric($p['value'])));

        if (count($points) < self::MIN_POINTS) {
            return [
                'available' => false,
                'reason' => sprintf(
                    'Needs at least %d data points to project — you have %d. Keep logging and this will fill in.',
                    self::MIN_POINTS,
                    count($points),
                ),
            ];
        }

        $base = PeriodService::bucketDate($points[0]['label']);
        if ($base === null) {
            return ['available' => false, 'reason' => 'Could not read the dates on this series.'];
        }

        $x = [];
        $y = [];
        foreach ($points as $p) {
            $date = PeriodService::bucketDate($p['label']);
            if ($date === null) {
                continue;
            }
            $x[] = (float) $base->diffInDays($date);
            $y[] = (float) $p['value'];
        }

        // All points landing on one date gives a vertical fit with no meaning.
        if (count($x) < self::MIN_POINTS || count(array_unique($x)) < 2) {
            return ['available' => false, 'reason' => 'Not enough distinct dates to establish a trend.'];
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
            'n' => count($x),
            'quality' => $this->quality($reg->r2, count($x)),
            'horizons' => $horizons,
            'dampened' => $dampen,
        ];
    }

    /**
     * R-squared alone overstates confidence on tiny samples — a handful of
     * points will sit near any line by chance. Cap the reported quality until
     * there is enough data for the fit to mean something.
     */
    private function quality(float $r2, int $n): string
    {
        if ($n < 6) {
            return 'weak';
        }

        return match (true) {
            $r2 >= 0.75 && $n >= 8 => 'strong',
            $r2 >= 0.4 => 'moderate',
            default => 'weak',
        };
    }
}
