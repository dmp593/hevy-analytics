<?php

namespace App\Services\Analytics;

use App\Models\BodyMeasurement;
use App\Models\User;
use App\Science\BodyComp\BodyComposition;
use App\Science\Stats\LinearRegression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BodyCompAnalytics
{
    /**
     * One instance per user per request. A dashboard render used to build
     * five of these across controllers, alerts and nutrition — each re-running
     * the same measurement queries. Same lifecycle as SetQuery's memo:
     * per-process, flushed by tests via flushMemo().
     *
     * @var array<int, self>
     */
    private static array $instances = [];

    /** All of this user's measurements, fetched once, oldest first. */
    private ?Collection $allMeasurements = null;

    /** Nutrition-page weigh-ins (date => kg), fetched once. */
    private ?array $intakeWeights = null;

    public static function for(User $user): self
    {
        return self::$instances[$user->id] ??= new self($user);
    }

    public static function flushMemo(): void
    {
        self::$instances = [];
    }

    /** Every measurement, oldest first — the one query the others derive from. */
    private function all(): Collection
    {
        return $this->allMeasurements ??= $this->user->bodyMeasurements()
            ->orderBy('date')->get();
    }

    private ?Collection $manualFatMap = null;

    public function __construct(private readonly User $user) {}

    public function bodyFatSource(): string
    {
        return $this->user->body_fat_source ?: 'scale';
    }

    /**
     * Resolve the body-fat % for a measurement honoring the user's chosen
     * source (scale BIA / Navy tape / manual). Falls back to the scale value
     * when the chosen source can't be computed for that date.
     */
    public function effectiveFatPercent(BodyMeasurement $m): ?float
    {
        $scale = $m->fat_percent !== null ? (float) $m->fat_percent : null;

        return match ($this->bodyFatSource()) {
            'navy' => $this->navyFor($m) ?? $scale,
            'manual' => $this->manualFatFor($m->date) ?? $scale,
            default => $scale,
        };
    }

    /**
     * The two Navy equations use different measurement sites: men's takes the
     * abdomen at the navel, women's takes the natural (narrowest) waist. Feeding
     * the abdomen into the women's equation overstates body fat by several
     * points, so pick the right column per sex and only fall back when the
     * preferred one is missing.
     */
    private function navyFor(BodyMeasurement $m): ?float
    {
        $height = $this->user->height_cm;
        $isFemale = BodyComposition::isFemale($this->user->sex);

        $circumference = $isFemale
            ? ($m->waist ?? $m->abdomen)
            : ($m->abdomen ?? $m->waist);

        if (! $height || ! $m->neck_cm || ! $circumference) {
            return null;
        }

        return BodyComposition::navyBodyFat(
            $this->user->sex ?? 'male',
            (float) $m->neck_cm,
            (float) $circumference,
            (float) $height,
            $m->hips !== null ? (float) $m->hips : null,
        );
    }

    private function manualFatFor(Carbon $date): ?float
    {
        if ($this->manualFatMap === null) {
            $this->manualFatMap = $this->user->intakeLogs()
                ->whereNotNull('fat_percent')
                ->orderBy('date')
                ->get(['date', 'fat_percent']);
        }

        // Nearest manual entry within 14 days of the measurement date.
        $best = null;
        $bestDiff = 15;
        foreach ($this->manualFatMap as $row) {
            $diff = abs($row->date->diffInDays($date));
            if ($diff <= 14 && $diff < $bestDiff) {
                $best = (float) $row->fat_percent;
                $bestDiff = $diff;
            }
        }

        return $best;
    }

    /**
     * @return Collection<int, BodyMeasurement>
     *
     * The single history entry point for body data — series() reads through it —
     * so the entitlement floor is applied here and nowhere else.
     *
     * latest(), latestValue() and symmetry() deliberately do NOT clamp. Those
     * answer "what am I right now", and an athlete on the free tier should still
     * see today's weight. It is the depth of history that is capped, not the
     * existence of their current numbers.
     */
    public function measurements(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from = $this->user->entitlements()->clampFrom($from);

        return $this->all()
            ->when($from, fn ($c) => $c->filter(fn ($m) => $m->date->greaterThanOrEqualTo($from)))
            ->when($to, fn ($c) => $c->filter(fn ($m) => $m->date->lessThanOrEqualTo($to)))
            ->values();
    }

    public function latest(): ?object
    {
        return $this->all()->last();
    }

    /** Latest known value for a column (searching back in time). */
    private function latestValue(string $column): ?float
    {
        $m = $this->all()->reverse()->first(fn ($m) => $m->{$column} !== null);

        return $m !== null ? (float) $m->{$column} : null;
    }

    /** Time series for one measurement column. */
    public function series(string $column, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $points = $this->measurements($from, $to)
            ->filter(fn ($m) => $m->{$column} !== null)
            ->mapWithKeys(fn ($m) => [$m->date->toDateString() => (float) $m->{$column}]);

        // Bodyweight has a second honest source: weigh-ins typed on the
        // Nutrition page. The guide promises they count, so they do — for
        // the trend, the EWMA and the charts alike. A synced measurement
        // wins on a shared date; the entitlement floor applies equally.
        if ($column === 'weight_kg') {
            $floor = $this->user->entitlements()->clampFrom($from);
            $this->intakeWeights ??= $this->user->intakeLogs()
                ->whereNotNull('weight_kg')->orderBy('date')
                ->get(['date', 'weight_kg'])
                ->mapWithKeys(fn ($l) => [$l->date->toDateString() => (float) $l->weight_kg])
                ->all();

            foreach ($this->intakeWeights as $key => $kg) {
                if ($points->has($key)) {
                    continue;
                }
                if ($floor && $key < $floor->toDateString()) {
                    continue;
                }
                if ($to && $key > $to->toDateString()) {
                    continue;
                }
                $points->put($key, $kg);
            }
        }

        return $points->sortKeys()
            ->map(fn ($value, $label) => ['label' => $label, 'value' => $value])
            ->values()->all();
    }

    /** Effective body-fat % time series (per chosen source). */
    public function fatPercentSeries(?Carbon $from = null, ?Carbon $to = null): array
    {
        return $this->measurements($from, $to)->map(function ($m) {
            $fat = $this->effectiveFatPercent($m);

            return $fat !== null ? ['label' => $m->date->toDateString(), 'value' => $fat] : null;
        })->filter()->values()->all();
    }

    /**
     * Derived lean-mass series (uses lean_mass_kg when present, else
     * weight×(1−fat%) with fat% from the chosen body-fat source).
     */
    public function leanMassSeries(?Carbon $from = null, ?Carbon $to = null): array
    {
        return $this->measurements($from, $to)->map(function ($m) {
            $fat = $this->effectiveFatPercent($m);
            $lean = $m->lean_mass_kg
                ?? ($m->weight_kg && $fat !== null
                    ? BodyComposition::leanMassFromFat((float) $m->weight_kg, $fat)
                    : null);

            return $lean !== null ? ['label' => $m->date->toDateString(), 'value' => $lean] : null;
        })->filter()->values()->all();
    }

    public function ffmiSeries(?Carbon $from = null, ?Carbon $to = null): array
    {
        $h = $this->user->height_cm;
        if (! $h) {
            return [];
        }

        return collect($this->leanMassSeries($from, $to))->map(function ($p) use ($h) {
            $ffmi = BodyComposition::ffmiNormalized($p['value'], $h);

            return $ffmi !== null ? ['label' => $p['label'], 'value' => $ffmi] : null;
        })->filter()->values()->all();
    }

    /**
     * Snapshot of current body-composition status.
     */
    public function status(): array
    {
        $weight = $this->latestValue('weight_kg');
        $scaleFat = $this->latestValue('fat_percent');
        $height = $this->user->height_cm;
        $neck = $this->latestValue('neck_cm');
        $abdomen = $this->latestValue('abdomen') ?? $this->latestValue('waist');
        $waist = $this->latestValue('waist');
        $hips = $this->latestValue('hips');

        // Women's Navy equation takes the natural waist, men's the abdomen.
        $navySite = BodyComposition::isFemale($this->user->sex)
            ? ($waist ?? $abdomen)
            : $abdomen;

        $navyBf = BodyComposition::navyBodyFat(
            $this->user->sex ?? 'male',
            $neck,
            $navySite,
            $height !== null ? (float) $height : null,
            $hips,
        );

        // Effective body-fat per chosen source.
        $fat = match ($this->bodyFatSource()) {
            'navy' => $navyBf ?? $scaleFat,
            'manual' => $this->manualFatFor(Carbon::now()) ?? $scaleFat,
            default => $scaleFat,
        };

        $lean = null;
        if ($weight !== null && $fat !== null) {
            $lean = BodyComposition::leanMassFromFat($weight, $fat);
        } elseif ($weight !== null && $height) {
            $lean = BodyComposition::boerLbm($weight, $height, $this->user->sex ?? 'male');
        }

        $whr = ($waist && $hips) ? BodyComposition::waistToHipRatio($waist, $hips) : null;

        return [
            'weight_kg' => $weight,
            'trend_weight_kg' => $this->trendWeightKg(),
            'fat_percent' => $fat,
            'fat_source' => $this->bodyFatSource(),
            'scale_fat_percent' => $scaleFat,
            'navy_fat_percent' => $navyBf,
            // A third, tape-only estimator shown for triangulation — never
            // silently substituted for the chosen source.
            'rfm_percent' => ($waist && $height)
                ? BodyComposition::relativeFatMass((float) $height, $waist, $this->user->sex)
                : null,
            'lean_mass_kg' => $lean,
            'fat_mass_kg' => ($weight && $fat !== null) ? BodyComposition::fatMassKg($weight, $fat) : null,
            'ffmi' => ($lean && $height) ? BodyComposition::ffmi($lean, $height) : null,
            'ffmi_normalized' => ($lean && $height) ? BodyComposition::ffmiNormalized($lean, $height) : null,
            'waist_to_height' => ($waist && $height) ? BodyComposition::waistToHeightRatio($waist, $height) : null,
            'waist_to_hip' => $whr,
            // WHO cut-offs are sex-specific (0.90 men / 0.85 women); with no
            // stated sex there is no honest judgement, only the number.
            'waist_to_hip_risk' => ($whr !== null && $this->user->sex !== null)
                ? $whr >= (BodyComposition::isFemale($this->user->sex) ? 0.85 : 0.90)
                : null,
            'symmetry' => $this->symmetry(),
        ];
    }

    public function symmetry(): array
    {
        $m = $this->all()->reverse()->values();
        $pairs = [
            'bicep' => ['left_bicep_cm', 'right_bicep_cm'],
            'forearm' => ['left_forearm_cm', 'right_forearm_cm'],
            'thigh' => ['left_thigh', 'right_thigh'],
            'calf' => ['left_calf', 'right_calf'],
        ];
        $out = [];
        foreach ($pairs as $name => [$l, $r]) {
            $left = $m->firstWhere(fn ($x) => $x->{$l} !== null)?->{$l};
            $right = $m->firstWhere(fn ($x) => $x->{$r} !== null)?->{$r};
            $pct = BodyComposition::symmetryPct($left ? (float) $left : null, $right ? (float) $right : null);
            if ($pct !== null) {
                $out[] = ['part' => $name, 'left' => (float) $left, 'right' => (float) $right, 'diff_pct' => $pct];
            }
        }

        return $out;
    }

    /**
     * Trend weight: a time-aware exponential moving average of the recent
     * scale readings (half-life 10 days).
     *
     * The classic trend-weight method: a single reading swings 1-2 kg on
     * water and meal timing alone, so the number a tile shows should be the
     * trend, not this morning's noise. Calculations keep using the raw
     * series — the regressions already smooth — this exists for display.
     */
    public function trendWeightKg(int $days = 35, float $halfLifeDays = 10.0): ?float
    {
        $series = $this->series('weight_kg', Carbon::now()->subDays($days));

        if ($series === []) {
            return null;
        }

        $trend = null;
        $previous = null;

        foreach ($series as $point) {
            $date = Carbon::parse($point['label']);

            if ($trend === null) {
                $trend = $point['value'];
            } else {
                // Gap-aware smoothing: alpha grows with the days elapsed, so
                // sparse loggers converge at the same rate as daily ones.
                $gap = max(1, $previous->diffInDays($date));
                $alpha = 1 - exp(-M_LN2 * $gap / $halfLifeDays);
                $trend = $trend + $alpha * ($point['value'] - $trend);
            }

            $previous = $date;
        }

        return round($trend, 2);
    }

    /**
     * Rate of body-weight change in kg/week using a linear fit over the window.
     */
    public function weightRateKgPerWeek(int $days = 42): ?array
    {
        $from = Carbon::now()->subDays($days);
        $series = $this->series('weight_kg', $from);
        if (count($series) < 2) {
            return null;
        }

        $x = [];
        $y = [];
        $base = Carbon::parse($series[0]['label']);
        foreach ($series as $p) {
            // Days elapsed SINCE the first point. Carbon 3's diffInDays is
            // signed, so the receiver must be the earlier date or every slope
            // comes out negated.
            $x[] = $base->diffInDays(Carbon::parse($p['label']));
            $y[] = $p['value'];
        }
        $reg = LinearRegression::fit($x, $y);

        return [
            'kg_per_week' => round($reg->slope * 7, 3),
            'pct_bw_per_week' => end($y) > 0 ? round($reg->slope * 7 / end($y) * 100, 3) : null,
            'r2' => round($reg->r2, 3),
            'current_weight' => end($y),
        ];
    }

    /**
     * Fit a linear trend to a labelled series and return slope-derived deltas.
     *
     * @param  array<int, array{label:string, value:float}>  $series
     * @return array{slope_per_day:float, delta:float, r2:float, n:int}|null
     */
    private function fitSeries(array $series, int $days): ?array
    {
        if (count($series) < 2) {
            return null;
        }
        $base = Carbon::parse($series[0]['label']);
        $x = [];
        $y = [];
        foreach ($series as $p) {
            // See weightRateKgPerWeek(): the earlier date must be the receiver.
            $x[] = (float) $base->diffInDays(Carbon::parse($p['label']));
            $y[] = (float) $p['value'];
        }
        $reg = LinearRegression::fit($x, $y);

        return [
            'slope_per_day' => $reg->slope,
            // Over the OBSERVED span, not the requested window: 30 days of
            // measurements inside a 90-day window must not report a delta
            // extrapolated across days nobody measured.
            'delta' => $reg->slope * max($x),
            'r2' => round($reg->r2, 3),
            'n' => count($series),
        ];
    }

    /** Trend of a measurement column expressed per 30 days (for triangulation). */
    public function trendPerMonth(string $column, int $days = 120): ?array
    {
        $series = $column === 'lean_mass'
            ? $this->leanMassSeries(Carbon::now()->subDays($days))
            : $this->series($column, Carbon::now()->subDays($days));

        $fit = $this->fitSeries($series, $days);
        if (! $fit) {
            return null;
        }

        return [
            'per_month' => round($fit['slope_per_day'] * 30, 2),
            'r2' => $fit['r2'],
            'n' => $fit['n'],
        ];
    }

    /**
     * Gain partitioning (p-ratio) computed from linear TRENDS over the window
     * rather than two raw endpoints — far less sensitive to single noisy BIA
     * readings. Returns a confidence flag so callers can soften messaging.
     */
    public function partitioning(int $days = 90): ?array
    {
        $from = Carbon::now()->subDays($days);
        $leanFit = $this->fitSeries($this->leanMassSeries($from), $days);
        $weightFit = $this->fitSeries($this->series('weight_kg', $from), $days);
        $fatFit = $this->fitSeries($this->fatPercentSeries($from), $days);

        $deltaLean = $leanFit['delta'] ?? null;
        $deltaWeight = $weightFit['delta'] ?? null;
        $deltaFatPct = $fatFit['delta'] ?? null;

        $pRatio = ($deltaLean !== null && $deltaWeight !== null && abs($deltaWeight) > 0.4)
            ? round($deltaLean / $deltaWeight, 2)
            : null;

        // Reliability: enough fat% points, a non-trivial weight move, and a
        // weight trend that actually fits the data.
        $fatPoints = $fatFit['n'] ?? 0;
        $reliable = $pRatio !== null
            && $fatPoints >= 5
            && ($weightFit['r2'] ?? 0) >= 0.25
            && abs($deltaWeight) >= 0.8;

        return [
            'delta_lean_kg' => $deltaLean !== null ? round($deltaLean, 2) : null,
            'delta_weight_kg' => $deltaWeight !== null ? round($deltaWeight, 2) : null,
            'delta_fat_pct' => $deltaFatPct !== null ? round($deltaFatPct, 2) : null,
            'p_ratio' => $pRatio,
            'reliable' => $reliable,
            'fat_points' => $fatPoints,
            'weight_r2' => $weightFit['r2'] ?? null,
            'source' => $this->bodyFatSource(),
        ];
    }
}
