<?php

namespace App\Http\Controllers;

use App\Science\Volume\MuscleLandmarks;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\MuscleBalance;
use App\Services\Analytics\VolumeAnalytics;
use Illuminate\Http\Request;

class MuscleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('muscle.index', array_merge($this->payload($request), [
            'routines' => $user->routines()->orderBy('title')->get(),
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
        $volume = new VolumeAnalytics($user, $filter);

        return [
            'filter' => $filter,
            'weeklySetsPerMuscle' => $volume->weeklySetsPerMuscle(),
            'volumePerMuscle' => $volume->volumePerMuscle(),
            'balance' => (new MuscleBalance($user, $filter))->ratios(),
            'landmarks' => MuscleLandmarks::LANDMARKS,
        ];
    }
}
