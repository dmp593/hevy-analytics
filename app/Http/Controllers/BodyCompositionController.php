<?php

namespace App\Http\Controllers;

use App\Science\Stats\LinearRegression;
use App\Services\Analytics\BodyCompAnalytics;
use App\Services\Analytics\BodyVerdict;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\StrengthAnalytics;
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
            'status' => $bc->status(),
            'rate' => $rate,
            'partitioning' => $bc->partitioning(),
            'triangulation' => $triangulation,
            // Read the corroborating signals together and say, in words, what
            // they agree on. This leads the page: the numbers below are the
            // evidence for it, not a puzzle for the athlete to solve.
            'verdict' => (new BodyVerdict($triangulation))->verdict(),
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
}
