<?php

namespace App\Services\Import;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Imports daily steps and sleep from the CSVs health apps export.
 *
 * Apple Health's native export is one all-or-nothing XML archive, so the
 * practical road is the same as everywhere else in this app: a CSV, from
 * tools like Health Auto Export ("Date, Step Count (count), Sleep Analysis
 * [Asleep] (hr)"), Fitbit's exports, or any hand-made file with a date plus
 * a steps and/or sleep column.
 *
 * Rows finer than a day (hourly exports) are summed per date. Sleep columns
 * declare hours or minutes in the header; when they declare nothing, values
 * up to 24 are read as hours — nobody sleeps 14 minutes a night for a month.
 *
 * Idempotent like the nutrition import: one intake row per date, touching
 * only the fields the file carries — a health import must never blank a
 * calorie total or a logged weight.
 */
class HealthCsvImport
{
    public const MAX_ROWS = 100_000;

    private const MAX_DAILY_STEPS = 150_000;

    private const MAX_SLEEP_MINUTES = 18 * 60;

    public function __construct(private readonly User $user) {}

    /**
     * @return array{days: int, skipped: int, errors: array<int, string>}
     *
     * @throws ImportException when the file as a whole cannot be understood.
     */
    public function run(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new ImportException(__('app.import.errors.unreadable'));
        }

        try {
            return $this->parse($handle);
        } finally {
            fclose($handle);
        }
    }

    /** @param resource $handle */
    private function parse($handle): array
    {
        $firstLine = fgets($handle);

        if ($firstLine === false) {
            throw new ImportException(__('app.import.errors.not_csv'));
        }

        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $header = str_getcsv(rtrim($firstLine, "\r\n"), $delimiter);

        $dateCol = null;
        $stepsCol = null;
        $sleepCol = null;
        $sleepInMinutes = null;

        foreach ($header as $i => $name) {
            $name = mb_strtolower(trim((string) $name));

            if ($dateCol === null && in_array($name, ['date', 'day', 'date/time', 'start'], true)) {
                $dateCol = $i;
            }

            if ($stepsCol === null && str_contains($name, 'step')) {
                $stepsCol = $i;
            }

            // "Sleep Analysis [Asleep] (hr)", "sleep hours", "minutes asleep"…
            if ($sleepCol === null && (str_contains($name, 'sleep') || str_contains($name, 'asleep'))) {
                $sleepCol = $i;
                $sleepInMinutes = str_contains($name, 'min') ? true : (str_contains($name, 'hr') || str_contains($name, 'hour') ? false : null);
            }
        }

        if ($dateCol === null || ($stepsCol === null && $sleepCol === null)) {
            throw new ImportException(__('app.health.errors.not_health'));
        }

        // First pass: sum per date, so hourly exports collapse into days.
        $days = [];
        $skipped = 0;
        $errors = [];
        $rows = 0;

        while (($row = fgetcsv($handle, null, $delimiter)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            if (++$rows > self::MAX_ROWS) {
                throw new ImportException(__('app.import.errors.too_many_rows', ['max' => number_format(self::MAX_ROWS)]));
            }

            $date = $this->parseDate(isset($row[$dateCol]) ? trim((string) $row[$dateCol]) : '');

            if ($date === null) {
                $skipped++;

                if (count($errors) < 5) {
                    $errors[] = __('app.import.errors.bad_date', ['row' => $rows + 1, 'value' => mb_substr(trim((string) ($row[$dateCol] ?? '')), 0, 30)]);
                }

                continue;
            }

            $days[$date] ??= ['steps' => null, 'sleep' => null];

            $numeric = function (?int $col) use ($row): ?float {
                if ($col === null || ! isset($row[$col])) {
                    return null;
                }
                $raw = str_replace(',', '', trim((string) $row[$col]));

                return is_numeric($raw) ? (float) $raw : null;
            };

            if (($steps = $numeric($stepsCol)) !== null) {
                $days[$date]['steps'] = ($days[$date]['steps'] ?? 0) + $steps;
            }

            if (($sleep = $numeric($sleepCol)) !== null) {
                // Header said hours, said minutes, or said nothing — in which
                // case a value that could be a night in hours is one.
                $minutes = match ($sleepInMinutes) {
                    true => $sleep,
                    false => $sleep * 60,
                    default => $sleep <= 24 ? $sleep * 60 : $sleep,
                };
                $days[$date]['sleep'] = ($days[$date]['sleep'] ?? 0) + $minutes;
            }
        }

        if ($days === []) {
            throw new ImportException(__('app.health.errors.nothing_found'));
        }

        // Second pass: upsert, touching only what the file carried, skipping
        // days no human produced.
        $written = 0;

        foreach ($days as $date => $totals) {
            if (($totals['steps'] !== null && $totals['steps'] > self::MAX_DAILY_STEPS)
                || ($totals['sleep'] !== null && $totals['sleep'] > self::MAX_SLEEP_MINUTES)) {
                $skipped++;

                if (count($errors) < 5) {
                    $errors[] = __('app.health.errors.absurd_day', ['date' => $date]);
                }

                continue;
            }

            if ($totals['steps'] === null && $totals['sleep'] === null) {
                continue;
            }

            $log = $this->user->intakeLogs()->whereDate('date', $date)->first()
                ?? $this->user->intakeLogs()->make(['date' => $date]);

            if ($totals['steps'] !== null) {
                $log->steps = (int) round($totals['steps']);
            }

            if ($totals['sleep'] !== null) {
                $log->sleep_minutes = (int) round($totals['sleep']);
            }

            $log->save();
            $written++;
        }

        return ['days' => $written, 'skipped' => $skipped, 'errors' => $errors];
    }

    /** Dates, tolerating the timestamp prefixes hourly exports carry. */
    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'm/d/Y', 'd/m/Y', 'm/d/y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
