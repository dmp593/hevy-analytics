<?php

namespace App\Services\Import;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Imports daily nutrition totals from the CSVs diet apps export.
 *
 * Same story as training data: MyFitnessPal closed its API to new partners,
 * Cronometer and Lose It never had one — their exports are the only road in.
 * The app only needs DAILY TOTALS (calories and macros feed the adaptive
 * maintenance measurement), so per-meal and per-food rows are summed per
 * date and nothing finer is kept.
 *
 * Dialects, matched by header signature:
 *  - MyFitnessPal: one row per MEAL (Date, Meal, Calories, ...) — summed.
 *  - Cronometer:   one row per DAY (Date, Energy (kcal), ...) — as is.
 *  - Lose It!:     one row per FOOD (Date, Name, Type, Calories, ...) —
 *                  summed, rows marked Deleted excluded.
 *  - Generic:      any file with a date and a calories column.
 *
 * Idempotent the same way workouts are: one intake row per date, a re-upload
 * fills in or corrects. Only the fields the file carries are touched — a
 * nutrition import must never blank a weight logged that morning.
 */
class NutritionCsvImport
{
    public const MAX_ROWS = 100_000;

    private const ALIASES = [
        'date' => ['date', 'day'],
        'calories' => ['calories', 'calories_(kcal)', 'energy_(kcal)', 'energy', 'kcal'],
        'protein' => ['protein_(g)', 'protein', 'proteins'],
        'fat' => ['fat_(g)', 'fat', 'total_fat'],
        'carbs' => ['carbohydrates_(g)', 'carbs_(g)', 'carbohydrates', 'carbs'],
        'meal' => ['meal'],
        'name' => ['name'],
        'type' => ['type'],
        'deleted' => ['deleted'],
    ];

    public function __construct(private readonly User $user) {}

    /**
     * @return array{days: int, skipped: int, errors: array<int, string>, source: string}
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

        $columns = [];

        foreach ($header as $i => $name) {
            $name = str_replace(' ', '_', mb_strtolower(trim((string) $name)));

            foreach (self::ALIASES as $canonical => $aliases) {
                if (in_array($name, $aliases, true) && ! isset($columns[$canonical])) {
                    $columns[$canonical] = $i;
                }
            }
        }

        if (! isset($columns['date'], $columns['calories'])) {
            throw new ImportException(__('app.nutrition.import.errors.not_nutrition'));
        }

        $source = match (true) {
            isset($columns['meal']) => 'mfp',
            isset($columns['name'], $columns['type']) => 'loseit',
            in_array('energy_(kcal)', array_map(fn ($h) => str_replace(' ', '_', mb_strtolower(trim((string) $h))), $header), true) => 'cronometer',
            default => 'generic',
        };

        // First pass: sum per date. Meal and per-food rows collapse into the
        // daily totals the analytics actually consume.
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

            $get = fn (string $key) => isset($columns[$key], $row[$columns[$key]])
                ? trim((string) $row[$columns[$key]])
                : '';

            // Lose It keeps deleted entries in the export, flagged.
            if (in_array(mb_strtolower($get('deleted')), ['true', '1', 'yes'], true)) {
                continue;
            }

            $date = $this->parseDate($get('date'));

            if ($date === null) {
                $skipped++;

                if (count($errors) < 5) {
                    $errors[] = __('app.import.errors.bad_date', ['row' => $rows + 1, 'value' => mb_substr($get('date'), 0, 30)]);
                }

                continue;
            }

            $days[$date] ??= ['calories' => null, 'protein' => null, 'fat' => null, 'carbs' => null];

            foreach (['calories', 'protein', 'fat', 'carbs'] as $field) {
                $raw = $get($field);

                if ($raw === '' || ! is_numeric(str_replace(',', '', $raw))) {
                    continue;
                }

                $days[$date][$field] = ($days[$date][$field] ?? 0) + (float) str_replace(',', '', $raw);
            }
        }

        if ($days === []) {
            throw new ImportException(__('app.nutrition.import.errors.nothing_found'));
        }

        // Second pass: upsert one intake row per date, touching only what the
        // file carried. A day whose total is beyond any human intake is a
        // parsing problem, not a meal — skipped and said.
        $written = 0;

        foreach ($days as $date => $totals) {
            if ($totals['calories'] !== null && $totals['calories'] > 20000) {
                $skipped++;

                if (count($errors) < 5) {
                    $errors[] = __('app.nutrition.import.errors.absurd_day', ['date' => $date]);
                }

                continue;
            }

            $log = $this->user->intakeLogs()->whereDate('date', $date)->first()
                ?? $this->user->intakeLogs()->make(['date' => $date]);

            if ($totals['calories'] !== null) {
                $log->calories = (int) round($totals['calories']);
            }

            if ($totals['protein'] !== null) {
                $log->protein_g = (int) round($totals['protein']);
            }

            if ($totals['fat'] !== null) {
                $log->fat_g = (int) round($totals['fat']);
            }

            if ($totals['carbs'] !== null) {
                $log->carb_g = (int) round($totals['carbs']);
            }

            $log->save();
            $written++;
        }

        return ['days' => $written, 'skipped' => $skipped, 'errors' => $errors, 'source' => $source];
    }

    /** Dates only — diet apps export days, not clock times. */
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
