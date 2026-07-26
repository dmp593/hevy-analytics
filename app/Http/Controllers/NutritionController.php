<?php

namespace App\Http\Controllers;

use App\Services\Analytics\NutritionService;
use Illuminate\Http\Request;

class NutritionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $service = new NutritionService($user);
        $target = $service->computeTargets();

        return view('nutrition.index', [
            'target' => $target,
            'goal' => $user->activeGoal(),
            'adherence' => $service->adherence(),
            'adaptive' => $service->adaptiveMaintenance(),
            'recentLogs' => $user->intakeLogs()->orderByDesc('date')->limit(14)->get(),
            'history' => $user->nutritionTargets()->orderByDesc('date')->limit(30)->get(),
        ]);
    }

    public function recompute(Request $request)
    {
        (new NutritionService($request->user()))->computeTargets();

        return redirect()->route('nutrition')->with('status', 'Targets recomputed from your latest body data.');
    }

    public function storeIntake(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'calories' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'protein_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'fat_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'carb_g' => ['nullable', 'numeric', 'min:0', 'max:3000'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'fat_percent' => ['nullable', 'numeric', 'min:0', 'max:70'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $log = $request->user()->intakeLogs()->whereDate('date', $data['date'])->first()
            ?? $request->user()->intakeLogs()->make(['date' => $data['date']]);
        $log->fill($data)->save();

        return redirect()->route('nutrition')->with('status', 'Intake logged.');
    }
}
