<?php

namespace App\Http\Controllers;

use App\Services\Analytics\BodyCompAnalytics;
use App\Services\Analytics\CheckInComparison;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Check-in comparison: two to four dates side by side. The judgement rules
 * and table assembly live in CheckInComparison; this controller only
 * validates the request and gathers the two date-keyed collections.
 */
class CompareController extends Controller
{
    public const MAX_DATES = 4;

    public const MIN_DATES = 2;

    public function index(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'dates' => ['nullable', 'array', 'max:'.self::MAX_DATES],
            'dates.*' => ['date'],
        ]);

        $photosByDate = $user->progressPhotos()->orderByDesc('date')->get()
            ->groupBy(fn ($p) => $p->date->toDateString());

        // Through the entitlement chokepoint on purpose: measurements beyond
        // the free tier's history window stay behind the same wall here as on
        // every chart. Photos are not gated anywhere, so they show regardless.
        $measurementsByDate = (new BodyCompAnalytics($user))->measurements()
            ->keyBy(fn ($m) => $m->date->toDateString());

        $candidates = $photosByDate->keys()
            ->merge($measurementsByDate->keys())
            ->unique()->sortDesc()->take(60)->values()
            ->map(fn ($d) => [
                'date' => $d,
                'poses' => $photosByDate->get($d)?->count() ?? 0,
                'measured' => $measurementsByDate->has($d),
            ]);

        // Oldest first: the baseline column reads left to right, whatever
        // order the boxes were ticked in.
        $selected = collect($validated['dates'] ?? [])
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()->sort()->values();

        return view('compare.index', [
            'candidates' => $candidates,
            'selected' => $selected,
            'comparison' => $selected->count() >= self::MIN_DATES
                ? (new CheckInComparison($user))->build($selected, $photosByDate, $measurementsByDate)
                : null,
        ]);
    }
}
