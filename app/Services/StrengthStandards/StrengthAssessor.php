<?php

namespace App\Services\StrengthStandards;

use App\Science\Strength\StrengthStandards;

/**
 * Resolves a strength-level assessment for a lift using a layered fallback:
 *   1. FitnessVolt API   (gym + verified percentiles, age-adjusted)
 *   2. OpenPowerlifting   (verified percentiles, precomputed, big-3)
 *   3. Built-in model     (ratio-based, offline, widest coverage)
 *
 * Returns a normalized structure consumed by the strength-bar component.
 */
class StrengthAssessor
{
    public function __construct(
        private readonly FitnessVoltClient $fitnessVolt,
        private readonly OpenPowerliftingStandards $opl,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function assess(string $exerciseTitle, float $e1rmKg, float $bodyweightKg, string $sex, ?int $age): ?array
    {
        $key = StrengthStandards::keyForTitle($exerciseTitle);
        // Some FitnessVolt/OPL lifts may map even if the built-in key is set;
        // key drives slug/lift lookups and the built-in fallback.

        if ($key) {
            if ($fv = $this->tryFitnessVolt($key, $e1rmKg, $bodyweightKg, $sex, $age)) {
                return $this->decorate($fv, $e1rmKg, $bodyweightKg, $sex, $age);
            }
            if ($opl = $this->tryOpl($key, $e1rmKg, $bodyweightKg, $sex)) {
                return $this->decorate($opl, $e1rmKg, $bodyweightKg, $sex, $age);
            }
        }

        if ($builtin = $this->tryBuiltin($key, $e1rmKg, $bodyweightKg, $sex, $age)) {
            return $this->decorate($builtin, $e1rmKg, $bodyweightKg, $sex, $age);
        }

        return null;
    }

    private function tryFitnessVolt(string $key, float $e1rm, float $bw, string $sex, ?int $age): ?array
    {
        $slug = $this->fitnessVolt->slugFor($key);
        if (! $slug) {
            return null;
        }
        $r = $this->fitnessVolt->rank($slug, $sex, $bw, $e1rm, $age);
        if (! $r) {
            return null;
        }

        // Headline = gym percentile (matches general lifter apps); fall back to
        // verified / fv_score if the gym leg is missing.
        $gym = $r['gym']['percentile'] ?? null;
        $verified = $r['verified']['percentile'] ?? null;
        $headline = $gym ?? $verified ?? $r['fv_score'];

        $populations = [];
        if ($gym !== null) {
            $populations['gym'] = ['percentile' => $gym, 'sample' => $r['gym']['sample_size'] ?? null];
        }
        if ($verified !== null) {
            $populations['verified'] = ['percentile' => $verified, 'sample' => $r['verified']['sample_size'] ?? null];
        }

        return [
            'percentile' => (float) $headline,
            'source' => 'fitnessvolt',
            'populations' => $populations,
            'attribution' => $r['attribution'] ?? null,
        ];
    }

    private function tryOpl(string $key, float $e1rm, float $bw, string $sex): ?array
    {
        if (! $this->opl->available()) {
            return null;
        }
        $r = $this->opl->percentile($key, $sex, $bw, $e1rm);
        if (! $r) {
            return null;
        }

        return [
            'percentile' => (float) $r['percentile'],
            'source' => 'openpowerlifting',
            'populations' => ['verified' => ['percentile' => $r['percentile'], 'sample' => $r['sample_size'] ?? null]],
            'attribution' => [
                'text' => 'OpenPowerlifting (CC0)',
                'url' => 'https://www.openpowerlifting.org/',
                'license' => 'CC0',
            ],
        ];
    }

    private function tryBuiltin(?string $key, float $e1rm, float $bw, string $sex, ?int $age): ?array
    {
        if (! $key) {
            return null;
        }
        $eval = StrengthStandards::evaluate($key, $e1rm, $bw, $sex, $age);
        if (! $eval) {
            return null;
        }

        return [
            'percentile' => (float) $eval['percentile'],
            'source' => 'builtin',
            'thresholds_kg' => $eval['thresholds_kg'],
            'age_factor' => $eval['age_factor'],
            'ratio' => $eval['ratio'],
        ];
    }

    private function decorate(array $assessment, float $e1rm, float $bw, string $sex, ?int $age): array
    {
        $p = max(0, min(100, $assessment['percentile']));

        return array_merge([
            'available' => true,
            'level' => StrengthStandards::levelForPercentile($p),
            'level_index' => StrengthStandards::levelIndexForPercentile($p),
            'boundaries' => StrengthStandards::LEVEL_BOUNDARIES,
            'e1rm' => round($e1rm, 1),
            'bodyweight' => round($bw, 1),
            'ratio' => round($e1rm / max(0.01, $bw), 2),
            'sex' => in_array(strtolower($sex), ['female', 'f', 'woman'], true) ? 'female' : 'male',
            'age' => $age,
        ], $assessment, ['percentile' => $p]);
    }
}
