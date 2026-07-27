<?php

namespace App\Http\Controllers;

use App\Services\Import\HevyCsvImport;
use App\Services\Import\ImportException;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function index(Request $request)
    {
        return view('import.index', [
            'workoutCount' => $request->user()->workouts()->count(),
        ]);
    }

    /**
     * Runs inline rather than on the queue, deliberately. A sync is dozens of
     * third-party API round-trips; parsing a file already in hand is seconds
     * of local work, and the person is sitting on the page waiting to hear
     * whether their file was any good. The row cap bounds the worst case.
     */
    public function store(Request $request)
    {
        $request->validate([
            // 20 MB holds a decade of training at Hevy's row sizes.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        try {
            $result = (new HevyCsvImport($request->user()))
                ->run($request->file('file')->getRealPath());
        } catch (ImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = __('app.import.done', [
            'workouts' => number_format($result['workouts']),
            'sets' => number_format($result['sets']),
        ]);

        if ($result['skipped'] > 0) {
            $message .= ' '.__('app.import.done_skipped', ['count' => number_format($result['skipped'])]);
        }

        return redirect()->route('dashboard')->with('status', $message);
    }
}
