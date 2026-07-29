<?php

namespace App\Http\Controllers;

use App\Services\Analytics\NutritionService;
use App\Services\Analytics\NutritionVerdict;
use App\Services\Import\HealthCsvImport;
use App\Services\Import\ImportException;
use App\Services\Import\NutritionCsvImport;
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
            'health' => $this->healthSummary($user),
        ]);
    }

    /**
     * Average steps and sleep over the last 14 days, plus the one judgement
     * they support: whether the stated activity level matches the observed
     * step count. The formula TDEE inherits any mismatch, so this is where a
     * wrong setting is worth a sentence — the adaptive blend corrects it
     * eventually, but only after weeks of logging.
     *
     * @return array{avg_steps: int|null, avg_sleep_minutes: int|null, activity_hint: string|null}
     */
    private function healthSummary($user): array
    {
        $recent = $user->intakeLogs()
            ->where('date', '>=', Carbon::now()->subDays(14))
            ->get(['steps', 'sleep_minutes']);

        $steps = $recent->whereNotNull('steps');
        $sleep = $recent->whereNotNull('sleep_minutes');

        $avgSteps = $steps->count() >= 5 ? (int) round($steps->avg('steps')) : null;
        $avgSleep = $sleep->count() >= 5 ? (int) round($sleep->avg('sleep_minutes')) : null;

        $activity = (float) ($user->activity_level ?? 1.55);
        $hint = null;

        if ($avgSteps !== null) {
            if ($avgSteps < 6000 && $activity >= 1.725) {
                $hint = 'sedentary_but_set_high';
            } elseif ($avgSteps > 12000 && $activity <= 1.375) {
                $hint = 'active_but_set_low';
            }
        }

        return [
            'avg_steps' => $avgSteps,
            'avg_sleep_minutes' => $avgSleep,
            'activity_hint' => $hint,
        ];
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

    /**
     * Daily totals from another diet app's CSV. Inline like the workout
     * import — the person is on the page waiting to hear if their file
     * was any good, and the row cap bounds the worst case.
     */
    public function importCsv(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        try {
            $result = (new NutritionCsvImport($request->user()))
                ->run($request->file('file')->getRealPath());
        } catch (ImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = trans_choice('app.nutrition.import.done', $result['days'], [
            'days' => number_format($result['days']),
            'source' => __('app.nutrition.import.source_'.$result['source']),
        ]);

        if ($result['skipped'] > 0) {
            $message .= ' '.__('app.import.done_skipped', ['count' => number_format($result['skipped'])]);
        }

        return redirect()->route('nutrition')->with('status', $message);
    }

    /** Daily steps and sleep from a health app's CSV export. */
    public function importHealthCsv(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        try {
            $result = (new HealthCsvImport($request->user()))
                ->run($request->file('file')->getRealPath());
        } catch (ImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = trans_choice('app.health.done', $result['days'], [
            'days' => number_format($result['days']),
        ]);

        if ($result['skipped'] > 0) {
            $message .= ' '.__('app.import.done_skipped', ['count' => number_format($result['skipped'])]);
        }

        return redirect()->route('nutrition')->with('status', $message);
    }
}
