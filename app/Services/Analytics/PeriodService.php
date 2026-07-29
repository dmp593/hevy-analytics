<?php

namespace App\Services\Analytics;

use Illuminate\Support\Carbon;

class PeriodService
{
    public const PERIODS = ['week', 'month', 'quarter', 'semester', 'year'];

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

    /**
     * Bucket key for grouping a date into the given period.
     *
     * Timestamps arrive in UTC; which day or week they belong to is a question
     * about the athlete's local calendar, so shift into their zone first. A
     * 21:00 session in Auckland is the previous day in UTC and would otherwise
     * be counted against the wrong week.
     */
    public static function bucketKey(Carbon $date, string $period, string $timezone = 'UTC'): string
    {
        $local = $date->copy()->setTimezone($timezone);

        return match ($period) {
            'week' => $local->copy()->startOfWeek()->toDateString(),
            'quarter' => $local->year.'-Q'.$local->quarter,
            'semester' => $local->year.'-S'.($local->month <= 6 ? 1 : 2),
            'year' => (string) $local->year,
            default => $local->format('Y-m'),
        };
    }

    /** The athlete's local calendar day for a UTC timestamp. */
    public static function localDate(Carbon $date, string $timezone = 'UTC'): string
    {
        return $date->copy()->setTimezone($timezone)->toDateString();
    }

    /**
     * Inverse of bucketKey(): turn a bucket label back into the date the bucket
     * starts on. Quarter ("2026-Q3"), semester ("2026-S2") and year ("2026")
     * labels are not parseable by Carbon, so anything that treats bucket labels
     * as dates must come through here.
     */
    public static function bucketDate(string $key): ?Carbon
    {
        if (preg_match('/^(\d{4})-Q([1-4])$/', $key, $m)) {
            return Carbon::create((int) $m[1], ((int) $m[2] - 1) * 3 + 1, 1)->startOfDay();
        }

        if (preg_match('/^(\d{4})-S([12])$/', $key, $m)) {
            return Carbon::create((int) $m[1], $m[2] === '1' ? 1 : 7, 1)->startOfDay();
        }

        if (preg_match('/^\d{4}$/', $key)) {
            return Carbon::create((int) $key, 1, 1)->startOfDay();
        }

        try {
            return Carbon::parse($key)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
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
