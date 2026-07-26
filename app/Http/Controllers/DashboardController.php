<?php

namespace App\Http\Controllers;

use App\Services\Analytics\BodyCompAnalytics;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\GoalAlerts;
use App\Services\Analytics\MuscleBalance;
use App\Services\Analytics\NutritionService;
use App\Services\Analytics\VolumeAnalytics;
use App\Services\Hevy\SyncStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->hasHevyKey()) {
            return view('dashboard', ['needsSetup' => true]);
        }

        $filter = new FilterCriteria(from: Carbon::now()->subDays(28), to: Carbon::now());
        $volume = new VolumeAnalytics($user, $filter);
        $rows = null;
        $bc = new BodyCompAnalytics($user);

        $tonnageFilter = new FilterCriteria(from: Carbon::now()->subMonths(6), to: Carbon::now(), period: 'week');

        return view('dashboard', [
            'needsSetup' => false,
            'status' => $bc->status(),
            'rate' => $bc->weightRateKgPerWeek(),
            'partitioning' => $bc->partitioning(),
            'alerts' => (new GoalAlerts($user))->all(),
            'goal' => $user->activeGoal(),
            'weekVolume' => $volume->tonnage(),
            'weekSets' => $volume->totalSets(),
            'weeklySetsPerMuscle' => $volume->weeklySetsPerMuscle(),
            'tonnageSeries' => (new VolumeAnalytics($user, $tonnageFilter))->tonnageSeries(),
            'weightSeries' => $bc->series('weight_kg', Carbon::now()->subMonths(12)),
            'leanSeries' => $bc->leanMassSeries(Carbon::now()->subMonths(12)),
            'balance' => (new MuscleBalance($user, new FilterCriteria(from: Carbon::now()->subMonths(3))))->ratios(),
            'workoutCount' => $user->workouts()->count(),
            'lastSync' => $user->hevy_last_synced_at,
            'syncStatus' => (new SyncStatus($user))->current(),
            'nutrition' => (new NutritionService($user))->computeTargets(),
        ]);
    }
}
