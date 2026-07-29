<?php

namespace App\Services\StrengthStandards;

use App\Science\BodyComp\BodyComposition;
use Illuminate\Support\Facades\Storage;

/**
 * OpenPowerlifting fallback: reads precomputed percentile tables built by
 * `php artisan strength:build-opl-standards` from the public OPL CSV (CC0).
 *
 * Covers the three competition lifts (squat, bench, deadlift), Raw equipment,
 * grouped by sex and bodyweight bucket. Verified competition population.
 */
class OpenPowerliftingStandards
{
    public const PATH = 'strength-standards/opl-standards.json';

    /** builtin key → OPL lift key. */
    private const LIFTS = [
        'squat' => 'squat',
        'bench_press' => 'bench',
        'deadlift' => 'deadlift',
    ];

    private ?array $data = null;

    public function available(): bool
    {
        return Storage::disk('local')->exists(self::PATH);
    }

    public function liftFor(string $builtinKey): ?string
    {
        return self::LIFTS[$builtinKey] ?? null;
    }

    private function load(): array
    {
        if ($this->data === null) {
            $this->data = $this->available()
                ? (json_decode(Storage::disk('local')->get(self::PATH), true) ?: [])
                : [];
        }

        return $this->data;
    }

    /**
     * Percentile (0–100) of an e1RM for a lift/sex/bodyweight, interpolated
     * from the precomputed breakpoints. Null if unavailable.
     */
    public function percentile(string $builtinKey, string $sex, float $bodyweightKg, float $e1rmKg): ?array
    {
        $lift = $this->liftFor($builtinKey);
        if (! $lift || $e1rmKg <= 0 || $bodyweightKg <= 0) {
            return null;
        }

        $data = $this->load();
        $sex = BodyComposition::normalizeSex($sex);
        $buckets = $data['lifts'][$lift][$sex] ?? null;
        if (! $buckets) {
            return null;
        }

        $bucket = $this->nearestBucket($buckets, $bodyweightKg);
        if (! $bucket) {
            return null;
        }

        $percentile = $this->interpolatePercentile($bucket['breakpoints'], $e1rmKg);

        return [
            'percentile' => $percentile,
            'sample_size' => $bucket['n'] ?? null,
            'source' => 'OpenPowerlifting',
            'generated' => $data['generated'] ?? null,
        ];
    }

    private function nearestBucket(array $buckets, float $bw): ?array
    {
        $best = null;
        $bestDiff = PHP_FLOAT_MAX;
        foreach ($buckets as $b) {
            $diff = abs(($b['bw'] ?? 0) - $bw);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $b;
            }
        }

        return $best;
    }

    /**
     * @param  array<int, array{p:float, w:float}>  $breakpoints  sorted by weight
     */
    private function interpolatePercentile(array $breakpoints, float $weight): float
    {
        if (empty($breakpoints)) {
            return 0.0;
        }
        if ($weight <= $breakpoints[0]['w']) {
            return round($breakpoints[0]['p'] * ($weight / max(0.01, $breakpoints[0]['w'])), 1);
        }
        $last = end($breakpoints);
        if ($weight >= $last['w']) {
            return round(min(99.9, $last['p']), 1);
        }
        for ($i = 0; $i < count($breakpoints) - 1; $i++) {
            $a = $breakpoints[$i];
            $b = $breakpoints[$i + 1];
            if ($weight <= $b['w']) {
                $frac = ($weight - $a['w']) / max(0.01, $b['w'] - $a['w']);

                return round($a['p'] + $frac * ($b['p'] - $a['p']), 1);
            }
        }

        return round($last['p'], 1);
    }
}
