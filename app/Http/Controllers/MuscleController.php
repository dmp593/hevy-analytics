<?php

namespace App\Http\Controllers;

use App\Science\Volume\MuscleLandmarks;
use App\Services\Analytics\EffortAnalysis;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\MuscleBalance;
use App\Services\Analytics\MuscleOverload;
use App\Services\Analytics\MuscleVerdict;
use App\Services\Analytics\PersonalScience;
use App\Services\Analytics\VolumeAnalytics;
use App\Support\AnalyticsCache;
use Illuminate\Http\Request;

class MuscleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('muscle.index', array_merge($this->payload($request), [
            'routines' => $user->routines()->orderBy('title')->get(),
            // Fixed 28-day window on purpose: effort is a recent habit, not a
            // property of whatever range the filter above happens to show.
            'effort' => (new EffortAnalysis($user))->summary(),
            'overload' => (new MuscleOverload($user))->perMuscle(),
            'portfolio' => (new PersonalScience($user))->repRangePortfolio(),
        ]));
    }

    public function data(Request $request)
    {
        return response()->view('muscle._results', $this->payload($request));
    }

    private function payload(Request $request): array
    {
        $user = $request->user();
        $filter = FilterCriteria::fromRequest($request, $request->user()->resolvedTimezone());

        $computed = AnalyticsCache::remember(
            $user,
            'muscle:'.md5(serialize($filter->toArray())),
            function () use ($user, $filter) {
                $volume = new VolumeAnalytics($user, $filter);
                $weeklySets = $volume->weeklySetsPerMuscle();
                $balance = (new MuscleBalance($user, $filter))->ratios();

                return [
                    'weeklySetsPerMuscle' => $weeklySets,
                    'volumePerMuscle' => $volume->volumePerMuscle(),
                    'balance' => $balance,
                ];
            },
        );
        $weeklySets = $computed['weeklySetsPerMuscle'];
        $balance = $computed['balance'];

        return [
            'filter' => $filter,
            'weeklySetsPerMuscle' => $weeklySets,
            'volumePerMuscle' => $computed['volumePerMuscle'],
            'balance' => $balance,
            'landmarks' => MuscleLandmarks::LANDMARKS,
            // Does the MEV subtraction the athlete would otherwise do by hand,
            // so the page opens with "chest needs 5 more sets" rather than with
            // ten bars and a legend.
            'verdict' => (new MuscleVerdict($weeklySets, $balance))->verdict(),
        ];
    }
}
