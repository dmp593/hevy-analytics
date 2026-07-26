<?php

namespace App\Http\Controllers;

use App\Services\Analytics\BodyCompAnalytics;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\ProjectionService;
use App\Services\Analytics\StrengthAnalytics;
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

        $projections = [
            'weight' => ['label' => 'Bodyweight (kg)', 'data' => $projector->project($bc->series('weight_kg', $from))],
            'lean_mass' => ['label' => 'Lean mass (kg)', 'data' => $projector->project($bc->leanMassSeries($from), dampen: true)],
            'fat_percent' => ['label' => 'Body fat (%)', 'data' => $projector->project($bc->series('fat_percent', $from))],
            'ffmi' => ['label' => 'Normalized FFMI', 'data' => $projector->project($bc->ffmiSeries($from), dampen: true)],
            'chest' => ['label' => 'Chest (cm)', 'data' => $projector->project($bc->series('chest_cm', $from))],
            'waist' => ['label' => 'Waist (cm)', 'data' => $projector->project($bc->series('waist', $from))],
            'bicep' => ['label' => 'Right bicep (cm)', 'data' => $projector->project($bc->series('right_bicep_cm', $from))],
        ];

        // Strength projection for the top lift
        $strengthFilter = new FilterCriteria(from: $from, to: Carbon::now());
        $prs = (new StrengthAnalytics($user, $strengthFilter))->exercisePrs();
        $topLift = $prs[0] ?? null;
        if ($topLift && $topLift['template_id']) {
            $liftFilter = new FilterCriteria(from: $from, to: Carbon::now(), exerciseTemplateHevyId: $topLift['template_id']);
            $e1rmSeries = (new StrengthAnalytics($user, $liftFilter))->e1rmSeries();
            $projections['top_lift'] = [
                'label' => ($topLift['exercise'] ?? 'Top lift').' e1RM (kg)',
                'data' => $projector->project($e1rmSeries, dampen: true),
            ];
        }

        return view('projections.index', [
            'projections' => $projections,
        ]);
    }
}
