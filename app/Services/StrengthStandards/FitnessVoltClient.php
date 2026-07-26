<?php

namespace App\Services\StrengthStandards;

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

        $sex = in_array(strtolower($sex), ['female', 'f', 'woman'], true) ? 'female' : 'male';
        $key = 'fv:rank:'.md5(implode('|', [$slug, $sex, round($bodyweightKg, 1), round($e1rmKg, 1), $age]));

        return Cache::remember($key, now()->addHours(12), function () use ($slug, $sex, $bodyweightKg, $e1rmKg, $age) {
            try {
                $response = Http::baseUrl($this->baseUrl())
                    ->timeout(8)
                    ->retry(1, 300, throw: false)
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
