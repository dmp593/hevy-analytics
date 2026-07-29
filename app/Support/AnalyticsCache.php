<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Cross-request cache for computed analytics payloads.
 *
 * A user\'s analytics inputs change a handful of times a day — a sync, a
 * weigh-in, an import — and every change already flows through a known
 * choke point. Those choke points bump the user\'s version; every cached
 * payload carries the version in its key, so invalidation is exact and
 * instant with no scanning. The date is in the key too: windows like
 * "last 28 days" move at midnight regardless of writes.
 *
 * Payloads must be plain arrays/scalars — models do not belong in here.
 */
class AnalyticsCache
{
    private const TTL_HOURS = 26;

    public static function version(User $user): int
    {
        return (int) Cache::get(self::versionKey($user), 1);
    }

    /** Call from every write path that changes what analytics would say. */
    public static function bump(User $user): void
    {
        // add() then increment(): increment on a missing key stores an
        // unexpirable raw value on some stores; this stays a plain integer.
        Cache::add(self::versionKey($user), 1, now()->addDays(30));
        Cache::increment(self::versionKey($user));
    }

    public static function remember(User $user, string $key, \Closure $compute): mixed
    {
        $tz = $user->resolvedTimezone();

        return Cache::remember(
            sprintf('ap:%d:v%d:%s:%s', $user->id, self::version($user), now($tz)->toDateString(), $key),
            now()->addHours(self::TTL_HOURS),
            $compute,
        );
    }

    private static function versionKey(User $user): string
    {
        return 'analytics:ver:'.$user->id;
    }
}
