<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Whether this account has enough history for the numbers to mean anything.
 *
 * Every metric here is a trend or a weekly rate, and both need data the
 * athlete may not have yet: a "weekly sets per muscle" figure computed from
 * four days of logging is an extrapolation wearing a measurement's clothes.
 * Individual pages already hedge (low-confidence labels, refusing to fit),
 * but nothing said it ONCE, plainly, account-wide — so a new user's first
 * impression was numbers that swing wildly day to day, which reads as a bug
 * in the product rather than a property of small samples.
 *
 * The thresholds are tied to what the analytics actually need. Weekly volume
 * stabilises after ~2 full training weeks; trends and rate-of-change need
 * roughly ten points over three weeks before a fitted slope beats noise.
 */
class DataConfidence
{
    public const MIN_SESSIONS = 10;

    public const MIN_SPAN_DAYS = 21;

    private function __construct(
        public readonly int $sessions,
        public readonly int $spanDays,
    ) {}

    public static function for(User $user): self
    {
        $range = $user->workouts()
            ->selectRaw('count(*) as n, min(start_time) as first, max(start_time) as last')
            ->first();

        $span = ($range->n ?? 0) > 1 && $range->first && $range->last
            ? (int) Carbon::parse($range->first)->diffInDays(Carbon::parse($range->last))
            : 0;

        return new self((int) ($range->n ?? 0), $span);
    }

    public function established(): bool
    {
        return $this->sessions >= self::MIN_SESSIONS
            && $this->spanDays >= self::MIN_SPAN_DAYS;
    }
}
