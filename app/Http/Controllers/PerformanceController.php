<?php

namespace App\Http\Controllers;

use App\Science\Volume\MuscleLandmarks;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\StrengthAnalytics;
use App\Services\Analytics\VolumeAnalytics;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filter = FilterCriteria::fromRequest($request);

        return view('performance.index', array_merge(
            $this->payload($request),
            [
                'filter' => $filter,
                'routines' => $user->routines()->orderBy('title')->get(),
                'exercises' => $user->exerciseTemplates()->orderBy('title')->get(),
                'muscles' => MuscleLandmarks::GROUPS,
            ]
        ));
    }

    public function data(Request $request)
    {
        return response()->view('performance._results', $this->payload($request));
    }

    private function payload(Request $request): array
    {
        $user = $request->user();
        $filter = FilterCriteria::fromRequest($request);
        $volume = new VolumeAnalytics($user, $filter);
        $strength = new StrengthAnalytics($user, $filter);

        $prs = $strength->exercisePrs();
        $best = $prs[0]['best_e1rm'] ?? null;

        return [
            'filter' => $filter,
            'tonnage' => $volume->tonnage(),
            'totalSets' => $volume->totalSets(),
            'totalReps' => $volume->totalReps(),
            'tonnageSeries' => $volume->tonnageSeries(),
            'e1rmSeries' => $filter->exerciseTemplateHevyId ? $strength->e1rmSeries() : [],
            'prs' => $prs,
            'strengthScores' => $best ? $strength->strengthScores($best) : null,
            'strengthLevel' => $filter->exerciseTemplateHevyId ? $strength->strengthLevelForCurrent() : null,
        ];
    }
}
