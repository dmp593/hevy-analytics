<?php

namespace App\Http\Controllers;

use App\Services\Import\CsvImport;
use App\Services\Import\ImportException;
use App\Services\Import\UnknownCsvFormat;
use App\Support\Units;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function index(Request $request)
    {
        return view('import.index', [
            'workoutCount' => $request->user()->workouts()->count(),
            'defaultUnit' => Units::for($request->user())->imperial() ? 'lbs' : 'kg',
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
        $data = $request->validate([
            // 20 MB holds a decade of training at Hevy's row sizes.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'unit' => ['nullable', 'in:kg,lbs'],
        ]);

        try {
            $result = (new CsvImport($request->user()))
                ->run($request->file('file')->getRealPath(), ['unit' => $data['unit'] ?? null]);
        } catch (UnknownCsvFormat $e) {
            // Not a dead end: park the file and let the person point each of
            // our fields at the right column themselves.
            Storage::disk('local')->put(
                $this->pendingPath($request),
                file_get_contents($request->file('file')->getRealPath()) ?: '',
            );

            return view('import.map', [
                'headers' => $e->headers,
                'preview' => $e->preview,
                'guess' => CsvImport::guess($e->headers),
                'unit' => $data['unit'] ?? (Units::for($request->user())->imperial() ? 'lbs' : 'kg'),
            ]);
        } catch (ImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard')->with('status', $this->doneMessage($result));
    }

    /** The column-matching screen's submit: import the parked file with the chosen mapping. */
    public function map(Request $request)
    {
        $data = $request->validate([
            'unit' => ['nullable', 'in:kg,lbs'],
            'map' => ['required', 'array'],
            'map.start_time' => ['required', 'integer', 'min:0', 'max:500'],
            'map.exercise_title' => ['required', 'integer', 'min:0', 'max:500'],
            'map.*' => ['nullable', 'integer', 'min:0', 'max:500'],
        ]);

        $path = $this->pendingPath($request);

        if (! Storage::disk('local')->exists($path)) {
            return redirect()->route('import')->with('error', __('app.import.map.expired'));
        }

        // Only fields the engine knows, only columns that were submitted.
        $map = collect($data['map'])
            ->only(CsvImport::MAPPABLE)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (int) $v)
            ->all();

        try {
            $result = (new CsvImport($request->user()))->run(
                Storage::disk('local')->path($path),
                ['map' => $map, 'unit' => $data['unit'] ?? null],
            );
        } catch (ImportException $e) {
            return redirect()->route('import')->with('error', $e->getMessage());
        } finally {
            Storage::disk('local')->delete($path);
        }

        return redirect()->route('dashboard')->with('status', $this->doneMessage($result));
    }

    /** One parked file per account: self-replacing, nothing to garbage-collect. */
    private function pendingPath(Request $request): string
    {
        return 'import-pending/'.$request->user()->id.'.csv';
    }

    private function doneMessage(array $result): string
    {
        $message = __('app.import.done', [
            'workouts' => number_format($result['workouts']),
            'sets' => number_format($result['sets']),
        ]);

        if (! in_array($result['source'], ['generic', 'mapped'], true)) {
            $message .= ' '.__('app.import.done_source', [
                'source' => __('app.import.source_'.$result['source']),
            ]);
        }

        if ($result['skipped'] > 0) {
            $message .= ' '.__('app.import.done_skipped', ['count' => number_format($result['skipped'])]);
        }

        return $message;
    }
}
