<?php

namespace App\Services\FatSecret;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Pulls a linked account's recent diary totals into the intake log.
 *
 * A small window re-synced daily, rather than "everything once": people edit
 * yesterday's meals, and re-reading a week each night keeps corrections
 * flowing while staying microscopic against the API quota (7 calls per user
 * per day against a 5,000/day allowance).
 */
class FatSecretSync
{
    public function __construct(private readonly FatSecretClient $client = new FatSecretClient) {}

    /** @return int days that received data */
    public function run(User $user, int $days = 7): int
    {
        $written = 0;

        foreach (range(0, $days - 1) as $back) {
            $day = Carbon::now($user->resolvedTimezone())->subDays($back);
            $totals = $this->client->dayTotals($user, $day);

            if ($totals === null) {
                continue;
            }

            $log = $user->intakeLogs()->whereDate('date', $day->toDateString())->first()
                ?? $user->intakeLogs()->make(['date' => $day->toDateString()]);

            // Same contract as every import: only the fields the source
            // carries are touched — never a logged weight or body fat.
            $log->calories = $totals['calories'];
            $log->protein_g = $totals['protein'];
            $log->fat_g = $totals['fat'];
            $log->carb_g = $totals['carbs'];
            $log->save();

            $written++;
        }

        $user->forceFill(['fatsecret_synced_at' => now()])->save();

        return $written;
    }
}
