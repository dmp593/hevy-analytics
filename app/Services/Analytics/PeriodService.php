<?php

namespace App\Services\Analytics;

use Illuminate\Support\Carbon;

class PeriodService
{
    public const PERIODS = ['week', 'month', 'quarter', 'semester', 'year'];

    /** Number of days in a projection horizon. */
    public static function horizonDays(string $period): int
    {
        return match ($period) {
            'week' => 7,
            'month' => 30,
            'quarter' => 91,
            'semester' => 182,
            'year' => 365,
            default => 30,
        };
    }

    public static function label(string $period): string
    {
        return match ($period) {
            'week' => 'Week',
            'month' => 'Month',
            'quarter' => 'Quarter',
            'semester' => 'Semester',
            'year' => 'Year',
            default => ucfirst($period),
        };
    }

    /** Bucket key for grouping a date into the given period. */
    public static function bucketKey(Carbon $date, string $period): string
    {
        return match ($period) {
            'week' => $date->copy()->startOfWeek()->toDateString(),
            'quarter' => $date->year.'-Q'.$date->quarter,
            'semester' => $date->year.'-S'.($date->month <= 6 ? 1 : 2),
            'year' => (string) $date->year,
            default => $date->format('Y-m'),
        };
    }

    /** All standard projection horizons. */
    public static function projectionHorizons(): array
    {
        return [
            'month' => 30,
            'quarter' => 91,
            'semester' => 182,
            'year' => 365,
        ];
    }
}
