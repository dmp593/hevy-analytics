<?php

namespace App\Http\Controllers;

use App\Services\Analytics\BodyCompAnalytics;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\ProjectionService;
use App\Services\Analytics\StrengthAnalytics;
use App\Support\Units;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProjectionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $bc = new BodyCompAnalytics($user);
        $projector = new ProjectionService;
        $from = Carbon::now()->subMonths(12);

        // `rising` says whether an increase in this metric is what the athlete
        // wants. Without it the view coloured every positive delta green,
        // including body fat and waist — telling the athlete the opposite of the
        // truth on the two metrics where being wrong matters most.
        //
        // Bodyweight is deliberately null: whether gaining is good depends on
        // the goal, so it is shown without a judgement rather than guessed at.
        $units = Units::for($user);
        $weight = fn (array $p) => $this->convert($p, fn ($v) => $units->weight($v, 2));
        $girth = fn (array $p) => $this->convert($p, fn ($v) => $units->girth($v, 2));

        $projections = [
            'weight' => ['label' => __('app.projections.weight', ['unit' => $units->weightUnit()]), 'rising' => null, 'data' => $weight($projector->project($bc->series('weight_kg', $from)))],
            'lean_mass' => ['label' => __('app.projections.lean_mass', ['unit' => $units->weightUnit()]), 'rising' => 'good', 'data' => $weight($projector->project($bc->leanMassSeries($from), dampen: true))],
            // fatPercentSeries, not series('fat_percent'): the raw column is the
            // scale reading, so an athlete using Navy tape or a manual estimate
            // saw one body-fat number on /body and a different one projected here.
            'fat_percent' => ['label' => __('app.projections.fat_percent'), 'rising' => 'bad', 'data' => $projector->project($bc->fatPercentSeries($from))],
            'ffmi' => ['label' => __('app.projections.ffmi'), 'rising' => 'good', 'data' => $projector->project($bc->ffmiSeries($from), dampen: true)],
            'chest' => ['label' => __('app.projections.chest', ['unit' => $units->girthUnit()]), 'rising' => 'good', 'data' => $girth($projector->project($bc->series('chest_cm', $from)))],
            'waist' => ['label' => __('app.projections.waist', ['unit' => $units->girthUnit()]), 'rising' => 'bad', 'data' => $girth($projector->project($bc->series('waist', $from)))],
            'bicep' => ['label' => __('app.projections.bicep', ['unit' => $units->girthUnit()]), 'rising' => 'good', 'data' => $girth($projector->project($bc->series('right_bicep_cm', $from)))],
        ];

        // Strength projection for the top lift
        $strengthFilter = new FilterCriteria(from: $from, to: Carbon::now());
        $prs = (new StrengthAnalytics($user, $strengthFilter))->exercisePrs();
        $topLift = $prs[0] ?? null;
        if ($topLift && $topLift['template_id']) {
            $liftFilter = new FilterCriteria(from: $from, to: Carbon::now(), exerciseTemplateHevyId: $topLift['template_id']);
            $e1rmSeries = (new StrengthAnalytics($user, $liftFilter))->e1rmSeries();
            $projections['top_lift'] = [
                'label' => __('app.projections.top_lift', ['exercise' => $topLift['exercise'] ?? '', 'unit' => $units->weightUnit()]),
                'rising' => 'good',
                'data' => $weight($projector->project($e1rmSeries, dampen: true)),
            ];
        }

        return view('projections.index', [
            'projections' => $projections,
        ]);
    }

    /**
     * Convert a projection result's numbers into the user's display unit.
     * Everything in the structure is linear in the metric value, so the
     * conversion distributes over current, slope, deltas and confidence.
     */
    private function convert(array $projection, callable $fn): array
    {
        if (! ($projection['available'] ?? false)) {
            return $projection;
        }

        $projection['current'] = $fn($projection['current']);
        $projection['slope_per_week'] = $fn($projection['slope_per_week']);

        foreach ($projection['horizons'] as $name => $horizon) {
            foreach (['value', 'delta', 'confidence'] as $key) {
                $projection['horizons'][$name][$key] = $fn($horizon[$key]);
            }
        }

        return $projection;
    }
}
