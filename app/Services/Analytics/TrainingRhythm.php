<?php

namespace App\Services\Analytics;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * When training actually happens: a calendar heatmap of hard sets per day,
 * and the rhythm behind it — typical session length, hours and weekdays.
 *
 * Everything here is read straight off the workout log; no goals, no
 * judgement. Rhythm is the one part of training people rarely see stated,
 * and seeing "you are a Monday 7am lifter" is worth more than it costs.
 */
class TrainingRhythm
{
    private const WEEKS = 26;

    public function __construct(private readonly User $user) {}

    /**
     * Which intensity bucket a day falls in, relative to the athlete's own
     * densest day — the ONE place the quartile thresholds live, so the two
     * calendar renderings can never disagree about what "dark" means.
     */
    public static function bucket(int $sets, int $max): string
    {
        if ($sets === 0 || $max <= 0) {
            return 'none';
        }
        $q = $sets / $max;

        return match (true) {
            $q > 0.75 => 'q4',
            $q > 0.5 => 'q3',
            $q > 0.25 => 'q2',
            default => 'q1',
        };
    }

    /**
     * @return array{
     *     days: array<string, int>,
     *     max: int,
     *     from: string,
     *     sessions: int,
     *     median_duration_min: int|null,
     *     top_hours: array<int, int>,
     *     top_weekdays: array<int, string>,
     * }|null
     */
    public function summary(): ?array
    {
        $tz = $this->user->resolvedTimezone();
        $now = Carbon::now($tz);

        $rows = (new SetQuery($this->user, new FilterCriteria(
            from: $now->copy()->subWeeks(self::WEEKS)->startOfWeek(),
            to: $now->copy()->endOfDay(),
        )))->rows();

        if ($rows->isEmpty()) {
            return null;
        }

        $days = [];
        $sessions = [];

        foreach ($rows as $r) {
            if (! $r->start_time) {
                continue;
            }
            $start = Carbon::parse($r->start_time)->setTimezone($tz);
            $days[$start->toDateString()] = ($days[$start->toDateString()] ?? 0) + 1;
            $sessions[$r->workout_id] ??= ['start' => $start, 'end' => $r->end_time];
        }

        $durations = [];
        $hours = [];
        $weekdays = [];

        foreach ($sessions as $s) {
            $hours[$s['start']->hour] = ($hours[$s['start']->hour] ?? 0) + 1;
            $weekdays[$s['start']->dayOfWeekIso] = ($weekdays[$s['start']->dayOfWeekIso] ?? 0) + 1;

            if ($s['end']) {
                $minutes = $s['start']->diffInMinutes(Carbon::parse($s['end'])->setTimezone($tz));
                // A "session" of five hours is a forgotten timer, not training.
                if ($minutes > 0 && $minutes <= 240) {
                    $durations[] = $minutes;
                }
            }
        }

        sort($durations);
        arsort($hours);
        arsort($weekdays);

        return [
            'days' => $days,
            'max' => $days === [] ? 0 : max($days),
            'from' => $now->copy()->subWeeks(self::WEEKS)->startOfWeek()->toDateString(),
            'sessions' => count($sessions),
            'median_duration_min' => $durations === []
                ? null
                : (int) round($durations[intdiv(count($durations), 2)]),
            'top_hours' => array_slice(array_keys($hours), 0, 2),
            'top_weekdays' => array_slice(array_keys($weekdays), 0, 2),
        ];
    }
}
