<?php

namespace App\Http\Controllers;

use App\Services\Analytics\NutritionService;
use App\Services\Analytics\NutritionVerdict;
use App\Support\Units;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NutritionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $service = new NutritionService($user);
        $target = $service->computeTargets();

        // The measurement needs 7 logged days to exist. Counting them here lets
        // the page say how many are still missing rather than only that the
        // targets are an estimate.
        $loggedDays = $user->intakeLogs()
            ->where('date', '>=', Carbon::now()->subDays(28))
            ->whereNotNull('calories')
            ->count();

        return view('nutrition.index', [
            'target' => $target,
            'goal' => $user->activeGoal(),
            'adherence' => $service->adherence(),
            'adaptive' => $service->adaptiveMaintenance(),
            'loggedDays' => $loggedDays,
            'verdict' => (new NutritionVerdict($target, $loggedDays))->verdict(),
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
        $units = Units::for($request->user());

        $data = $request->validate([
            'date' => ['required', 'date'],
            'calories' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'protein_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'fat_g' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'carb_g' => ['nullable', 'numeric', 'min:0', 'max:3000'],
            // Typed in the user's unit; stored metric.
            'weight' => ['nullable', 'numeric', 'min:0', 'max:'.($units->imperial() ? 1102 : 500)],
            'fat_percent' => ['nullable', 'numeric', 'min:0', 'max:70'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Only touch the stored weight when one was typed: an intake-only
        // submit must not blank a weight logged earlier that day.
        if (filled($data['weight'] ?? null)) {
            $data['weight_kg'] = $units->weightToKg($data['weight']);
        }
        unset($data['weight']);

        $log = $request->user()->intakeLogs()->whereDate('date', $data['date'])->first()
            ?? $request->user()->intakeLogs()->make(['date' => $data['date']]);
        $log->fill($data)->save();

        return redirect()->route('nutrition')->with('status', 'Intake logged.');
    }
}
