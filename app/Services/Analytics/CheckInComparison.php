<?php

namespace App\Services\Analytics;

use App\Http\Controllers\BodyCompositionController;
use App\Http\Controllers\ProgressPhotoController;
use App\Models\User;
use App\Support\Units;
use Illuminate\Support\Collection;

/**
 * Builds the check-in comparison table: columns per selected date, photos
 * aligned by pose, and each metric row's cells with display value, delta,
 * arrow and tone.
 *
 * Judgement is deliberately narrow. Only the weight and body-fat rows are
 * coloured, and only against the active goal — "bicep up" is good in a bulk
 * and ambiguous in a cut, and inventing verdicts for fourteen tape
 * measurements would be guessing. Colour never stands alone: the signed
 * number and arrow carry the same information for anyone who cannot tell red
 * from green.
 */
class CheckInComparison
{
    /**
     * A change smaller than this share of baseline bodyweight is water and
     * meal timing, not physique — shown amber, never judged.
     */
    private const WEIGHT_BAND_PCT = 0.01;

    private const WEIGHT_BAND_MIN_KG = 0.5;

    /**
     * Body-fat bands, in percentage points. Consumer estimates (BIA scales,
     * tape formulas) carry roughly a point of noise, so nothing inside the
     * band counts as a real change.
     */
    private const FAT_BAND_PP = 1.0;

    /** In a bulk, fat gain past this is flagged amber — never red, because some fat gain is the accepted cost. */
    private const FAT_BULK_WARN_PP = 2.0;

    /** Goal type => which way the goal wants bodyweight to move. */
    private const GOAL_DIRECTION = [
        'lean_bulk' => 'gain',
        'aggressive_bulk' => 'gain',
        'strength' => 'gain',
        'cut' => 'lose',
        'recomposition' => 'maintain',
        'hypertrophy' => 'maintain',
    ];

    public function __construct(private readonly User $user) {}

    public function build(Collection $dates, Collection $photosByDate, Collection $measurementsByDate): array
    {
        $units = Units::for($this->user);

        $columns = $dates->map(fn ($d) => [
            'date' => $d,
            // First photo per pose: pose is the row key that aligns columns.
            'photos' => ($photosByDate->get($d) ?? collect())
                ->groupBy('angle')->map(fn ($g) => $g->first()),
            'measurement' => $measurementsByDate->get($d),
        ])->values();

        $poses = collect(ProgressPhotoController::POSES)
            ->filter(fn ($pose) => $columns->contains(fn ($c) => $c['photos']->has($pose)))
            ->values();

        $goalType = $this->user->activeGoal()?->type;
        $goalDirection = self::GOAL_DIRECTION[$goalType] ?? null;

        $metrics = [
            ['key' => 'weight_kg', 'label' => __('app.body.measure.weight'), 'kind' => 'weight'],
            ['key' => 'fat_percent', 'label' => __('app.body.measure.fat_percent'), 'kind' => 'percent'],
            ['key' => 'lean_mass_kg', 'label' => __('app.dashboard.lean_mass'), 'kind' => 'weight'],
            ...collect(BodyCompositionController::GIRTHS)->map(fn ($column, $field) => [
                'key' => $column,
                'label' => __('app.body.measure.'.$field),
                'kind' => 'girth',
            ])->values(),
        ];

        $rows = collect($metrics)->map(function ($metric) use ($columns, $units, $goalDirection) {
            $raw = $columns->map(fn ($c) => $c['measurement']?->{$metric['key']} !== null
                ? (float) $c['measurement']->{$metric['key']}
                : null);

            if ($raw->filter(fn ($v) => $v !== null)->isEmpty()) {
                return null;
            }

            $baseline = $raw->first();

            $cells = $raw->map(function ($value, $i) use ($metric, $units, $baseline, $goalDirection) {
                if ($value === null) {
                    return ['display' => null, 'delta' => null, 'tone' => 'neutral', 'arrow' => null];
                }

                $display = match ($metric['kind']) {
                    'weight' => $units->weight($value),
                    'girth' => $units->girth($value),
                    default => round($value, 1),
                };

                if ($i === 0 || $baseline === null) {
                    return ['display' => $display, 'delta' => null, 'tone' => 'neutral', 'arrow' => null];
                }

                $deltaMetric = $value - $baseline;
                $deltaDisplay = match ($metric['kind']) {
                    'weight' => $units->weight($deltaMetric, 1),
                    'girth' => $units->girth($deltaMetric, 1),
                    default => round($deltaMetric, 1),
                };

                return [
                    'display' => $display,
                    'delta' => sprintf('%+.1f', $deltaDisplay),
                    'arrow' => abs($deltaMetric) < 0.05 ? '→' : ($deltaMetric > 0 ? '↑' : '↓'),
                    'tone' => match ($metric['key']) {
                        'weight_kg' => $this->judgeWeight($goalDirection, $deltaMetric, $baseline),
                        'fat_percent' => $this->judgeFat($goalDirection, $deltaMetric),
                        default => 'neutral',
                    },
                ];
            });

            return [
                'label' => $metric['label'],
                'unit' => match ($metric['kind']) {
                    'weight' => $units->weightUnit(),
                    'girth' => $units->girthUnit(),
                    default => '%',
                },
                'cells' => $cells->values()->all(),
            ];
        })->filter()->values()->all();

        return [
            'columns' => $columns,
            'poses' => $poses,
            'rows' => $rows,
            'goal_type' => $goalType,
            'goal_direction' => $goalDirection,
        ];
    }

    /** All judgements run on metric values; only display strings convert. */
    private function judgeWeight(?string $direction, float $deltaKg, float $baselineKg): string
    {
        if ($direction === null) {
            return 'neutral';
        }

        $band = max(self::WEIGHT_BAND_MIN_KG, $baselineKg * self::WEIGHT_BAND_PCT);
        $stable = abs($deltaKg) <= $band;

        return match ($direction) {
            // In maintenance, drifting out of the band is equally wrong in
            // either direction; the arrow says which way.
            'maintain' => $stable ? 'good' : 'bad',
            'gain' => $stable ? 'warn' : ($deltaKg > 0 ? 'good' : 'bad'),
            'lose' => $stable ? 'warn' : ($deltaKg < 0 ? 'good' : 'bad'),
        };
    }

    /**
     * Body-fat% change against the goal, in percentage points. A fall is good
     * under every goal; in a bulk a rise is only flagged (amber, never red)
     * once it outruns what a lean bulk needs — inside that, no judgement.
     */
    private function judgeFat(?string $direction, float $deltaPp): string
    {
        if ($direction === null) {
            return 'neutral';
        }

        return match ($direction) {
            'lose' => abs($deltaPp) <= self::FAT_BAND_PP
                ? 'warn'
                : ($deltaPp < 0 ? 'good' : 'bad'),
            'maintain' => $deltaPp > self::FAT_BAND_PP ? 'bad' : 'good',
            'gain' => match (true) {
                $deltaPp < -self::FAT_BAND_PP => 'good',
                $deltaPp > self::FAT_BULK_WARN_PP => 'warn',
                default => 'neutral',
            },
        };
    }
}
