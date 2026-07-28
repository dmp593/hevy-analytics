<?php

namespace App\Http\Controllers;

use App\Science\Stats\LinearRegression;
use App\Services\Analytics\BodyCompAnalytics;
use App\Services\Analytics\BodyVerdict;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\StrengthAnalytics;
use App\Support\Units;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BodyCompositionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $from = $request->query('from') ? Carbon::parse($request->query('from')) : Carbon::now()->subMonths(12);
        $bc = new BodyCompAnalytics($user);

        // Corroborating trend signals (triangulation) so no single BIA number dominates.
        $topLift = (new StrengthAnalytics(
            $user,
            new FilterCriteria(from: Carbon::now()->subMonths(4), to: Carbon::now())
        ))->exercisePrs();
        $liftE1rm = null;
        if (! empty($topLift) && ($topLift[0]['template_id'] ?? null)) {
            $liftFilter = new FilterCriteria(
                from: Carbon::now()->subMonths(4), to: Carbon::now(),
                exerciseTemplateHevyId: $topLift[0]['template_id'],
            );
            $series = (new StrengthAnalytics($user, $liftFilter))->e1rmSeries();
            if (count($series) >= 2) {
                $slope = LinearRegression::fit(
                    array_map(fn ($p, $i) => (float) $i, $series, array_keys($series)),
                    array_column($series, 'value'),
                )->slope;
                $liftE1rm = ['exercise' => $topLift[0]['exercise'], 'trend' => $slope >= 0 ? 'up' : 'down'];
            }
        }

        $rate = $bc->weightRateKgPerWeek();

        $triangulation = [
            'weight' => $rate,
            'waist' => $bc->trendPerMonth('waist'),
            'chest' => $bc->trendPerMonth('chest_cm'),
            'bicep' => $bc->trendPerMonth('right_bicep_cm'),
            'lift' => $liftE1rm,
        ];

        return view('body.index', [
            'from' => $from,
            'hasMeasurements' => $user->bodyMeasurements()->exists(),
            'status' => $bc->status(),
            'rate' => $rate,
            'partitioning' => $bc->partitioning(),
            'triangulation' => $triangulation,
            // Read the corroborating signals together and say, in words, what
            // they agree on. This leads the page: the numbers below are the
            // evidence for it, not a puzzle for the athlete to solve.
            'verdict' => (new BodyVerdict($triangulation, Units::for($user)))->verdict(),
            'weightSeries' => $bc->series('weight_kg', $from),
            'fatSeries' => $bc->fatPercentSeries($from),
            'leanSeries' => $bc->leanMassSeries($from),
            'ffmiSeries' => $bc->ffmiSeries($from),
            'chestSeries' => $bc->series('chest_cm', $from),
            'waistSeries' => $bc->series('waist', $from),
            'bicepSeries' => $bc->series('right_bicep_cm', $from),
            'symmetry' => $bc->symmetry(),
        ]);
    }

    /** Girth form fields => their storage columns (the schema mixes suffix styles). */
    public const GIRTHS = [
        'neck' => 'neck_cm',
        'shoulders' => 'shoulder_cm',
        'chest' => 'chest_cm',
        'left_bicep' => 'left_bicep_cm',
        'right_bicep' => 'right_bicep_cm',
        'left_forearm' => 'left_forearm_cm',
        'right_forearm' => 'right_forearm_cm',
        'abdomen' => 'abdomen',
        'waist' => 'waist',
        'hips' => 'hips',
        'left_thigh' => 'left_thigh',
        'right_thigh' => 'right_thigh',
        'left_calf' => 'left_calf',
        'right_calf' => 'right_calf',
    ];

    /**
     * Manual measurement entry — the door for accounts the API cannot feed.
     * Hevy's CSV export carries no body data, so without this form a CSV-only
     * athlete had no way to put weight, body fat or tape numbers into the app.
     *
     * The date is editable on purpose: a measurement taken Saturday and logged
     * Monday belongs to Saturday. One row per date; a second submit for the
     * same date fills in or corrects, and only the fields actually typed are
     * touched — logging today's waist must not blank this morning's synced
     * weight.
     */
    public function storeMeasurement(Request $request)
    {
        $user = $request->user();
        $units = Units::for($user);
        $girthRule = ['nullable', 'numeric', 'min:0', 'max:'.($units->imperial() ? 120 : 300)];

        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:'.now($user->resolvedTimezone())->toDateString()],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:'.($units->imperial() ? 1102 : 500)],
            'fat_percent' => ['nullable', 'numeric', 'min:0', 'max:70'],
            ...array_map(fn () => $girthRule, self::GIRTHS),
        ]);

        $m = $user->bodyMeasurements()->whereDate('date', $data['date'])->first()
            ?? $user->bodyMeasurements()->make(['date' => $data['date']]);

        if (filled($data['weight'] ?? null)) {
            $m->weight_kg = $units->weightToKg($data['weight']);
        }

        if (filled($data['fat_percent'] ?? null)) {
            $m->fat_percent = (float) $data['fat_percent'];
        }

        foreach (self::GIRTHS as $field => $column) {
            if (filled($data[$field] ?? null)) {
                $m->{$column} = $units->girthToCm($data[$field]);
            }
        }

        // Lean mass is derived, never asked: weight × (1 − fat%). Recomputed
        // whenever both parts are known, so the FFMI series stays consistent.
        if ($m->weight_kg !== null && $m->fat_percent !== null) {
            $m->lean_mass_kg = round($m->weight_kg * (1 - $m->fat_percent / 100), 2);
        }

        $m->save();

        return redirect()->route('body')->with('status', __('app.body.measure.saved'));
    }
}
