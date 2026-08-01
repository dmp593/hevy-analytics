<?php

namespace App\Http\Controllers;

use App\Support\AnalyticsCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The command palette's dynamic index: the athlete's own routines and the
 * exercises they have actually performed, as search targets. Static targets
 * (pages, actions) ship inside the palette component; only what varies per
 * account crosses this endpoint.
 */
class PaletteController extends Controller
{
    public function data(Request $request)
    {
        $user = $request->user();

        return response()->json(AnalyticsCache::remember($user, 'palette', function () use ($user) {
            $routines = $user->routines()->with('folder')
                ->orderByDesc('hevy_created_at')->limit(100)->get()
                ->map(fn ($r) => [
                    'title' => $r->title,
                    'context' => $r->folder?->title,
                    'url' => route('routines.show', $r->hevy_id),
                ])->values()->all();

            // Exercises with history first — the palette exists to reach YOUR
            // lifts, not to browse Hevy's four-hundred-row catalogue. Matched
            // by template id where the rollup carries one, by title where it
            // does not (CSV-born rows). A brand new account falls back to the
            // whole catalogue so search still works on day one.
            $performedIds = DB::table('workout_set_rollups')->where('user_id', $user->id)
                ->whereNotNull('exercise_template_hevy_id')->distinct()->pluck('exercise_template_hevy_id');
            $performedTitles = DB::table('workout_set_rollups')->where('user_id', $user->id)
                ->distinct()->pluck('exercise_title');

            $exercises = $user->exerciseTemplates()
                ->when($performedTitles->isNotEmpty(), fn ($q) => $q->where(
                    fn ($w) => $w->whereIn('hevy_id', $performedIds)->orWhereIn('title', $performedTitles)
                ))
                ->orderBy('title')->limit(300)->get()
                ->map(fn ($t) => [
                    'title' => $t->title,
                    'context' => null,
                    'url' => route('performance', ['exercise' => $t->hevy_id]),
                ])->values()->all();

            return ['routines' => $routines, 'exercises' => $exercises];
        }));
    }
}
