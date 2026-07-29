<?php

namespace App\Http\Controllers;

use App\Science\Volume\MuscleLandmarks;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\SetQuery;
use App\Services\Analytics\StrengthAnalytics;
use App\Services\Analytics\StrengthVerdict;
use App\Services\Analytics\VolumeAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PerformanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $boardFilter = new FilterCriteria(from: Carbon::now()->subWeeks(8), to: Carbon::now());
        $statusBoard = (new StrengthAnalytics($user, $boardFilter))->exerciseStatusBoard();

        return view('performance.index', array_merge(
            $this->payload($request),
            [
                'routines' => $user->routines()->orderBy('title')->get(),
                'muscles' => MuscleLandmarks::GROUPS,
                'statusBoard' => $statusBoard,
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
        $filter = FilterCriteria::fromRequest($request, $request->user()->resolvedTimezone());
        $volume = new VolumeAnalytics($user, $filter);
        $strength = new StrengthAnalytics($user, $filter);

        // One row fetch shared across every reader below. exercisePrs and
        // exerciseTrends each walk the same sets, and re-querying for the second
        // would double the page's cost for no new data.
        $rows = (new SetQuery($user, $filter))->rows();

        $prs = $strength->exercisePrs($rows);
        $best = $prs[0]['best_e1rm'] ?? null;
        $trends = $strength->exerciseTrends($rows);

        return [
            'filter' => $filter,
            // Rendered by _results, which the AJAX route returns on its own, so
            // it belongs in the shared payload rather than only in index().
            'exercises' => $user->exerciseTemplates()->orderBy('title')->get(),
            'trends' => $trends,
            // Volume answers "how much work did I do". The page's actual question
            // is whether the lifts are moving, which only per-lift e1RM can say.
            'verdict' => (new StrengthVerdict($trends, $prs))->verdict(),
            'tonnage' => $volume->tonnage(),
            'totalSets' => $volume->totalSets(),
            'totalReps' => $volume->totalReps(),
            'tonnageSeries' => $volume->tonnageSeries(),
            'e1rmSeries' => $filter->exerciseTemplateHevyId ? $strength->e1rmSeries($rows) : [],
            'prs' => $prs,
            'strengthScores' => $best ? $strength->strengthScores($best) : null,
            'strengthLevel' => $filter->exerciseTemplateHevyId ? $strength->strengthLevelForCurrent($rows) : null,
        ];
    }
}
