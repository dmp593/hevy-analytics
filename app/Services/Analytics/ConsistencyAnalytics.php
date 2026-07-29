<?php

namespace App\Services\Analytics;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Training consistency, stated descriptively.
 *
 * The evidence here is unusually strong and unusually simple: the 2026 ACSM
 * guidelines put "every major muscle at least twice a week, consistently"
 * above any programming detail, and consistency across the first four weeks
 * is the strongest known predictor of long-term adherence. This service
 * reports what happened; it does not invent a target the athlete never set.
 */
class ConsistencyAnalytics
{
    /** Window for the streak; per-muscle frequency reads the last 28 days of it. */
    private const WEEKS = 12;

    /**
     * Days per 28 that count as "about twice a week" for a muscle. Strictly
     * 2×/wk is 8; requiring 7 forgives one missed session in the month, which
     * is the difference between describing a habit and policing one.
     */
    private const FREQUENCY_DAYS = 7;

    public function __construct(private readonly User $user) {}

    /**
     * @return array{
     *     sessions_this_week: int,
     *     avg_per_week: float,
     *     streak_weeks: int,
     *     muscles_trained: int,
     *     muscles_at_frequency: int,
     *     early_days: bool,
     * }|null
     */
    public function summary(): ?array
    {
        $tz = $this->user->resolvedTimezone();
        $now = Carbon::now($tz);

        $filter = new FilterCriteria(
            from: $now->copy()->subWeeks(self::WEEKS)->startOfDay(),
            to: $now->copy()->endOfDay(),
        );
        $rows = (new SetQuery($this->user, $filter))->rows();

        if ($rows->isEmpty()) {
            return null;
        }

        $cutoff28 = $now->copy()->subDays(28)->startOfDay();
        $daysByWeek = [];
        $muscleDays = [];
        $days28 = [];

        foreach ($rows as $r) {
            if (! $r->start_time) {
                continue;
            }

            $date = Carbon::parse($r->start_time)->setTimezone($tz);
            $day = $date->toDateString();

            $daysByWeek[$date->format('o-W')][$day] = true;

            if ($date->greaterThanOrEqualTo($cutoff28)) {
                $days28[$day] = true;
                if ($r->primary_muscle_group) {
                    $muscleDays[$r->primary_muscle_group][$day] = true;
                }
            }
        }

        $atFrequency = count(array_filter($muscleDays, fn ($days) => count($days) >= self::FREQUENCY_DAYS));

        return [
            'sessions_this_week' => count($daysByWeek[$now->format('o-W')] ?? []),
            'avg_per_week' => round(count($days28) / 4, 1),
            'streak_weeks' => $this->streak($daysByWeek, $now),
            'muscles_trained' => count($muscleDays),
            'muscles_at_frequency' => $atFrequency,
            'early_days' => $this->firstWorkoutAt()?->greaterThanOrEqualTo($cutoff28) ?? false,
        ];
    }

    /**
     * Consecutive calendar weeks with at least one session, counted backwards.
     * The current week only extends the streak, never breaks it — a Monday
     * visit must not read "streak: 0" to someone who trained all of last week.
     */
    private function streak(array $daysByWeek, Carbon $now): int
    {
        $streak = 0;
        $week = $now->copy();

        if (isset($daysByWeek[$week->format('o-W')])) {
            $streak++;
        }

        for ($i = 1; $i <= self::WEEKS; $i++) {
            $week = $week->subWeek();
            if (! isset($daysByWeek[$week->format('o-W')])) {
                break;
            }
            $streak++;
        }

        return $streak;
    }

    /**
     * When this account's history begins — an aggregate on purpose, so the
     * "first 28 days" framing reads the account's true start rather than the
     * start of whatever window the entitlement allows.
     */
    private function firstWorkoutAt(): ?Carbon
    {
        $first = $this->user->workouts()->min('start_time');

        return $first ? Carbon::parse($first) : null;
    }
}
