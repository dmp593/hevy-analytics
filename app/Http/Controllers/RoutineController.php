<?php

namespace App\Http\Controllers;

use App\Models\Routine;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\RoutineAnalytics;
use App\Services\Analytics\StrengthAnalytics;
use App\Services\Analytics\VolumeAnalytics;
use App\Services\Hevy\RoutineAdvisor;
use App\Services\Hevy\RoutineProgression;
use App\Support\AnalyticsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RoutineController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filter = new FilterCriteria(from: Carbon::now()->subMonths(6), to: Carbon::now());

        // Newest first, grouped by the athlete's own Hevy folders. The
        // alphabetical flat list made duplicate titles ("Treino A" in two
        // folders) indistinguishable, and recent routines are almost always
        // the active programme. groupBy preserves the recency sort, so the
        // folder holding the newest routine also surfaces first.
        $routines = $user->routines()->withCount('exercises')->with('folder')->get()
            ->sortByDesc(fn ($r) => $r->hevy_created_at ?? $r->created_at)
            ->values();

        return view('routines.index', [
            'overview' => (new RoutineAnalytics($user, $filter))->overview(),
            'groups' => $routines->groupBy(fn ($r) => $r->folder?->title ?? ''),
            // Plain arrays, so the advisor's volume + trend passes ride the
            // analytics cache and cost one read until the next write bumps it.
            'advice' => AnalyticsCache::remember($user, 'routine-advisor',
                fn () => (new RoutineAdvisor($user))->suggestions()),
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
            // The recommendations themselves, visible before anything is
            // staged: "what should I do next session" must not require
            // committing to a write-back to find out.
            'suggestions' => (new RoutineProgression($request->user()))->build($routine)['changes'],
        ]);
    }
}
