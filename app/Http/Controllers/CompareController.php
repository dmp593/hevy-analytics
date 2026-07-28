<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Analytics\BodyCompAnalytics;
use App\Support\Units;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Check-in comparison: two to four dates side by side, photos aligned by
 * pose, biometric values with their change against the oldest selected date.
 *
 * Judgement is deliberately narrow. Only the weight row is coloured, and only
 * against the active goal — "bicep up" is good in a bulk and ambiguous in a
 * cut, and inventing verdicts for fourteen tape measurements would be
 * guessing. Colour never stands alone: the signed number and arrow carry the
 * same information for anyone who cannot tell red from green.
 */
class CompareController extends Controller
{
    public const MAX_DATES = 4;

    public const MIN_DATES = 2;

    /**
     * A change smaller than this share of baseline bodyweight is water and
     * meal timing, not physique — shown amber, never judged.
     */
    private const WEIGHT_BAND_PCT = 0.01;

    private const WEIGHT_BAND_MIN_KG = 0.5;

    /** Goal type => which way the goal wants bodyweight to move. */
    private const GOAL_DIRECTION = [
        'lean_bulk' => 'gain',
        'aggressive_bulk' => 'gain',
        'strength' => 'gain',
        'cut' => 'lose',
        'recomposition' => 'maintain',
        'hypertrophy' => 'maintain',
    ];

    public function index(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'dates' => ['nullable', 'array', 'max:'.self::MAX_DATES],
            'dates.*' => ['date'],
        ]);

        $photosByDate = $user->progressPhotos()->orderByDesc('date')->get()
            ->groupBy(fn ($p) => $p->date->toDateString());

        // Through the entitlement chokepoint on purpose: measurements beyond
        // the free tier's history window stay behind the same wall here as on
        // every chart. Photos are not gated anywhere, so they show regardless.
        $measurementsByDate = (new BodyCompAnalytics($user))->measurements()
            ->keyBy(fn ($m) => $m->date->toDateString());

        $candidates = $photosByDate->keys()
            ->merge($measurementsByDate->keys())
            ->unique()->sortDesc()->take(60)->values()
            ->map(fn ($d) => [
                'date' => $d,
                'poses' => $photosByDate->get($d)?->count() ?? 0,
                'measured' => $measurementsByDate->has($d),
            ]);

        // Oldest first: the baseline column reads left to right, whatever
        // order the boxes were ticked in.
        $selected = collect($validated['dates'] ?? [])
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()->sort()->values();

        return view('compare.index', [
            'candidates' => $candidates,
            'selected' => $selected,
            'comparison' => $selected->count() >= self::MIN_DATES
                ? $this->build($user, $selected, $photosByDate, $measurementsByDate)
                : null,
        ]);
    }

    private function build(User $user, Collection $dates, Collection $photosByDate, Collection $measurementsByDate): array
    {
        $units = Units::for($user);

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

        $goalType = $user->activeGoal()?->type;
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
                    'tone' => $metric['key'] === 'weight_kg'
                        ? $this->judgeWeight($goalDirection, $deltaMetric, $baseline)
                        : 'neutral',
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
}
