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

    private function navyFor(BodyMeasurement $m): ?float
    {
        $height = $this->user->height_cm;
        $abdomen = $m->abdomen ?? $m->waist;
        if (! $height || ! $m->neck_cm || ! $abdomen) {
            return null;
        }

        return BodyComposition::navyBodyFatMen((float) $m->neck_cm, (float) $abdomen, (float) $height);
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

    /** @return Collection<int, BodyMeasurement> */
    public function measurements(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        return $this->user->bodyMeasurements()
            ->when($from, fn ($q) => $q->where('date', '>=', $from))
            ->when($to, fn ($q) => $q->where('date', '<=', $to))
            ->orderBy('date')
            ->get();
    }

    public function latest(): ?object
    {
        return $this->user->bodyMeasurements()->orderByDesc('date')->first();
    }

    /** Latest known value for a column (searching back in time). */
    private function latestValue(string $column): ?float
    {
        $v = $this->user->bodyMeasurements()
            ->whereNotNull($column)->orderByDesc('date')->value($column);

        return $v !== null ? (float) $v : null;
    }

    /** Time series for one measurement column. */
    public function series(string $column, ?Carbon $from = null, ?Carbon $to = null): array
    {
        return $this->measurements($from, $to)
            ->filter(fn ($m) => $m->{$column} !== null)
            ->map(fn ($m) => ['label' => $m->date->toDateString(), 'value' => (float) $m->{$column}])
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

        $navyBf = ($neck && $abdomen && $height)
            ? BodyComposition::navyBodyFatMen($neck, $abdomen, $height)
            : null;

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

        return [
            'weight_kg' => $weight,
            'fat_percent' => $fat,
            'fat_source' => $this->bodyFatSource(),
            'scale_fat_percent' => $scaleFat,
            'navy_fat_percent' => $navyBf,
            'lean_mass_kg' => $lean,
            'fat_mass_kg' => ($weight && $fat !== null) ? BodyComposition::fatMassKg($weight, $fat) : null,
            'ffmi' => ($lean && $height) ? BodyComposition::ffmi($lean, $height) : null,
            'ffmi_normalized' => ($lean && $height) ? BodyComposition::ffmiNormalized($lean, $height) : null,
            'waist_to_height' => ($waist && $height) ? BodyComposition::waistToHeightRatio($waist, $height) : null,
            'waist_to_hip' => ($waist && $hips) ? BodyComposition::waistToHipRatio($waist, $hips) : null,
            'symmetry' => $this->symmetry(),
        ];
    }

    public function symmetry(): array
    {
        $m = $this->user->bodyMeasurements()->orderByDesc('date')->get();
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
            $x[] = Carbon::parse($p['label'])->diffInDays($base);
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
            $x[] = (float) Carbon::parse($p['label'])->diffInDays($base);
            $y[] = (float) $p['value'];
        }
        $reg = LinearRegression::fit($x, $y);

        return [
            'slope_per_day' => $reg->slope,
            'delta' => $reg->slope * $days,
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
