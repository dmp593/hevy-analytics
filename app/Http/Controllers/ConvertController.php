<?php

namespace App\Http\Controllers;

use App\Services\Export\Converter;
use App\Services\Export\LossManifest;
use App\Services\Import\ImportException;
use App\Services\Import\UnknownCsvFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The platform converter: history out in another app's CSV dialect.
 *
 * Preview first, always — the loss manifest (what this dialect cannot say,
 * counted from the person's actual rows) renders before any download exists.
 * The preview is free; the download is the paid feature. Someone deciding
 * whether to switch apps deserves to know what the move costs before being
 * asked to pay for the ticket.
 */
class ConvertController extends Controller
{
    public function index(Request $request)
    {
        return view('convert.index', [
            'targets' => Converter::TARGETS,
            'entitled' => $request->user()->entitlements()->canConvert(),
            'workoutCount' => $request->user()->workouts()->count(),
            'preview' => null,
            'input' => [],
        ]);
    }

    public function preview(Request $request)
    {
        $data = $this->validated($request);

        try {
            $parsed = $this->parsed($request, $data, storePending: true);
        } catch (UnknownCsvFormat) {
            // The matching screen lives on the import page; duplicating it
            // here would mean two of them drifting apart. Import first (which
            // maps the columns), then convert from the account.
            return back()->with('error', __('app.convert.unknown_file'));
        } catch (ImportException $e) {
            return back()->with('error', $e->getMessage());
        }

        $sets = collect($parsed['workouts'])
            ->flatMap(fn ($w) => $w['exercises'])
            ->sum(fn ($ex) => count($ex['sets']));

        return view('convert.index', [
            'targets' => Converter::TARGETS,
            'entitled' => $request->user()->entitlements()->canConvert(),
            'workoutCount' => $request->user()->workouts()->count(),
            'preview' => [
                'workouts' => count($parsed['workouts']),
                'sets' => $sets,
                'source' => $parsed['source'],
                'losses' => LossManifest::for($parsed['workouts'], $data['target']),
            ],
            'input' => $data,
        ]);
    }

    public function download(Request $request)
    {
        abort_unless($request->user()->entitlements()->canConvert(), 403);

        $data = $this->validated($request);

        try {
            $parsed = $this->parsed($request, $data, storePending: false);
        } catch (ImportException $e) {
            return redirect()->route('convert')->with('error', $e->getMessage());
        }

        $converter = new Converter($request->user());
        $csv = $converter->write($parsed['workouts'], $data['target']);

        if ($data['mode'] === 'file') {
            Storage::disk('local')->delete($this->pendingPath($request));
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$converter->filename($data['target']).'"',
        ]);
    }

    /** @return array{mode: string, target: string, unit: ?string, from: ?string, to: ?string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'mode' => ['required', 'in:file,account'],
            'target' => ['required', Rule::in(Converter::TARGETS)],
            'unit' => ['nullable', 'in:kg,lbs'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            // The file only travels with the preview; the download re-reads
            // the parked copy so the CSV cannot differ from what was shown.
            'file' => [$request->routeIs('convert.preview') ? 'required_if:mode,file' : 'nullable', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);
    }

    /** @return array{workouts: array, skipped: int, source: string} */
    private function parsed(Request $request, array $data, bool $storePending): array
    {
        $converter = new Converter($request->user());

        if ($data['mode'] === 'account') {
            return $converter->fromAccount(
                isset($data['from']) && $data['from'] ? Carbon::parse($data['from']) : null,
                isset($data['to']) && $data['to'] ? Carbon::parse($data['to']) : null,
            );
        }

        $pending = $this->pendingPath($request);

        if ($request->hasFile('file')) {
            if ($storePending) {
                Storage::disk('local')->put($pending, file_get_contents($request->file('file')->getRealPath()) ?: '');
            }

            return $converter->fromFile($request->file('file')->getRealPath(), ['unit' => $data['unit'] ?? null]);
        }

        if (! Storage::disk('local')->exists($pending)) {
            throw new ImportException(__('app.import.map.expired'));
        }

        return $converter->fromFile(Storage::disk('local')->path($pending), ['unit' => $data['unit'] ?? null]);
    }

    /** One parked file per account, self-replacing — same pattern as the import mapper. */
    private function pendingPath(Request $request): string
    {
        return 'convert-pending/'.$request->user()->id.'.csv';
    }
}
