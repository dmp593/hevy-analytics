<?php

namespace App\Http\Controllers;

use App\Models\Routine;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\RoutineAnalytics;
use App\Services\Analytics\StrengthAnalytics;
use App\Services\Analytics\VolumeAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RoutineController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filter = new FilterCriteria(from: Carbon::now()->subMonths(6), to: Carbon::now());

        return view('routines.index', [
            'overview' => (new RoutineAnalytics($user, $filter))->overview(),
            'routines' => $user->routines()->withCount('exercises')->orderBy('title')->get(),
        ]);
    }

    public function show(Request $request, Routine $routine)
    {
        $this->authorizeOwner($routine);

        $user = $request->user();
        $filter = new FilterCriteria(from: Carbon::now()->subMonths(12), to: Carbon::now(), routineHevyId: $routine->hevy_id);
        $analytics = new RoutineAnalytics($user, $filter);

        $exercises = $analytics->exercisesForRoutine($routine->hevy_id);
        $selectedExercise = $request->query('exercise');

        $e1rmSeries = [];
        if ($selectedExercise) {
            $exFilter = new FilterCriteria(
                from: Carbon::now()->subMonths(12),
                to: Carbon::now(),
                routineHevyId: $routine->hevy_id,
                exerciseTemplateHevyId: $selectedExercise,
            );
            $e1rmSeries = (new StrengthAnalytics($user, $exFilter))->e1rmSeries();
        }

        return view('routines.show', [
            'routine' => $routine->load('exercises'),
            'sessionSeries' => $analytics->sessionSeries($routine->hevy_id),
            'muscleCoverage' => $analytics->muscleCoverage($routine),
            'exercises' => $exercises,
            'selectedExercise' => $selectedExercise,
            'e1rmSeries' => $e1rmSeries,
            'volume' => new VolumeAnalytics($user, $filter),
        ]);
    }

    public function edit(Request $request, Routine $routine)
    {
        $this->authorizeOwner($routine);

        return view('routines.edit', [
            'routine' => $routine->load('exercises'),
            'templates' => $request->user()->exerciseTemplates()->orderBy('title')->get(),
        ]);
    }
}
