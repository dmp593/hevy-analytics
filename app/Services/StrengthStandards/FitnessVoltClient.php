<?php

namespace App\Services\StrengthStandards;

use App\Science\BodyComp\BodyComposition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Client for the free FitnessVolt Strength Standards API (CC BY 4.0).
 *
 * Serves two separate populations, never blended:
 *  - verified competition percentiles (OpenPowerlifting, big-3)
 *  - self-reported gym percentiles (Symmetric Strength, all lifts)
 *
 * @see https://fitnessvolt.com/strength-standards/developers/
 */
class FitnessVoltClient
{
    /** Hevy-style titles/keys → FitnessVolt lift slug. */
    private const SLUGS = [
        'squat' => 'squat',
        'bench_press' => 'bench-press',
        'deadlift' => 'deadlift',
        'front_squat' => 'front-squat',
        'sumo_deadlift' => 'sumo-deadlift',
        'incline_bench_press' => 'incline-bench-press',
        'overhead_press' => 'overhead-press',
        'power_clean' => 'power-clean',
        'push_press' => 'push-press',
    ];

    public function enabled(): bool
    {
        return (bool) config('services.fitnessvolt.enabled', true);
    }

    public function slugFor(string $builtinKey): ?string
    {
        return self::SLUGS[$builtinKey] ?? null;
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.fitnessvolt.base_url'), '/');
    }

    /**
     * Dual percentile + score + tier for a single lift attempt.
     *
     * @return array{fv_score:int, tier:string, gym:?array, verified:?array, attribution:array}|null
     */
    public function rank(string $slug, string $sex, float $bodyweightKg, float $e1rmKg, ?int $age = null): ?array
    {
        if (! $this->enabled() || $bodyweightKg <= 0 || $e1rmKg <= 0) {
            return null;
        }

        $sex = BodyComposition::normalizeSex($sex);
        // Coarse key on purpose: at 0.1 kg precision every weigh-in minted a
        // fresh cache miss and another upstream HTTP call. Percentiles do not
        // move inside a 2.5 kg bucket, so neither should the cache key — and
        // a week's TTL fits how often population standards change (never).
        $bwBucket = round($bodyweightKg / 2.5) * 2.5;
        $liftBucket = round($e1rmKg / 2.5) * 2.5;
        $key = 'fv:rank:'.md5(implode('|', [$slug, $sex, $bwBucket, $liftBucket, $age]));

        return Cache::remember($key, now()->addDays(7), function () use ($slug, $sex, $bodyweightKg, $e1rmKg, $age) {
            try {
                // 3 s, one try: this runs on the request path and the builtin
                // standards are a good fallback — a slow upstream must not pin
                // a PHP worker for 8+ seconds per lift.
                $response = Http::baseUrl($this->baseUrl())
                    ->timeout(3)
                    ->acceptJson()
                    ->get('/public/rank', array_filter([
                        'lift' => $slug,
                        'sex' => $sex,
                        'bw' => $bodyweightKg,
                        'e1rm' => $e1rmKg,
                        'age' => $age,
                        'unit' => 'kg',
                    ], fn ($v) => $v !== null));

                if (! $response->successful() || ! $response->json('success')) {
                    return null;
                }

                return [
                    'fv_score' => (int) $response->json('fv_score'),
                    'tier' => (string) $response->json('tier'),
                    'gym' => $response->json('gym'),
                    'verified' => $response->json('verified'),
                    'attribution' => $response->json('attribution', [
                        'text' => 'Powered by FitnessVolt',
                        'url' => 'https://fitnessvolt.com/strength-standards/',
                        'license' => 'CC BY 4.0',
                    ]),
                ];
            } catch (\Throwable) {
                return null;
            }
        });
    }
}
