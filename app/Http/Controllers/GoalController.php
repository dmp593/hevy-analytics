<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Science\Goals\GoalProfile;
use App\Science\Goals\GoalType;
use App\Services\Analytics\NutritionService;
use App\Support\AnalyticsCache;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('goals.index', [
            'types' => GoalType::all(),
            'presets' => collect(GoalType::keys())->mapWithKeys(fn ($k) => [$k => GoalProfile::preset($k)->toArray()]),
            'active' => $user->activeGoal(),
            'history' => $user->goals()->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', GoalType::keys())],
            'calorie_adjustment_pct' => ['nullable', 'numeric', 'between:-40,40'],
            'protein_g_per_kg' => ['nullable', 'numeric', 'between:1,4'],
            'fat_g_per_kg' => ['nullable', 'numeric', 'between:0.3,2'],
            'target_rate_pct_bw_per_week' => ['nullable', 'numeric', 'between:-2,2'],
        ]);

        $user = $request->user();
        $user->goals()->update(['is_active' => false]);

        $user->goals()->create(array_merge($data, [
            'is_active' => true,
            'started_at' => now(),
        ]));

        // Recompute nutrition targets for the new goal.
        (new NutritionService($user))->computeTargets();

        AnalyticsCache::bump($request->user());

        return redirect()->route('goals')->with('status', __('app.goals.saved'));
    }
}
