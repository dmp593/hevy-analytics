<?php

namespace App\Http\Controllers;

use App\Science\Strength\StrengthStandards;
use App\Services\Analytics\FilterCriteria;
use App\Services\Analytics\StrengthAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StrengthLevelController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filter = new FilterCriteria(from: Carbon::now()->subYears(2), to: Carbon::now());
        $analytics = new StrengthAnalytics($user, $filter);
        $levels = $analytics->strengthLevels();

        $bw = $user->bodyMeasurements()
            ->whereNotNull('weight_kg')->orderByDesc('date')->value('weight_kg');
        $ageFactor = $user->age ? StrengthStandards::ageFactor($user->age) : null;

        return view('strength-levels.index', [
            'levels' => $levels,
            'bodyweight' => $bw ? (float) $bw : null,
            'ageFactor' => $ageFactor,
            // Passed separately from the value actually used, so the view can
            // say the comparison was ASSUMED rather than stating it as fact.
            // Defaulting to male and then printing "compared against male
            // lifters" told every woman who had not filled in her profile that
            // she was being ranked against men, flatly and without warning.
            'sex' => $user->sex,
            'assumedSex' => $user->sex === null,
            'age' => $user->age,
        ]);
    }
}
