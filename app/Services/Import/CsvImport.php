<?php

namespace App\Services\Import;

use App\Models\User;
use App\Support\ExerciseMuscles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports workout CSVs — Hevy's own export and the other apps' dialects.
 *
 * This is the door for people no API can serve: Hevy issues API keys to Pro
 * subscribers only, and no other lifting app (Strong, FitNotes, Jefit…) has a
 * public API at all. Their CSV exports are therefore not a fallback but the
 * only road in, and each app writes a different file. The engine is one
 * pipeline with per-source knowledge:
 *
 *  - named sources are recognised by their header signature (SOURCES); a file
 *    nobody recognises raises UnknownCsvFormat so the controller can offer a
 *    column-matching screen instead of a rejection
 *  - weight columns in kg, lbs, unit-less with a unit column beside them, or
 *    unit-less with nothing at all — the last case falls back to the unit the
 *    athlete picked on the form ($options['unit'])
 *  - several date shapes, interpreted in the ATHLETE'S timezone — a CSV has
 *    no offset, and parsing "18:30" as UTC would shift evening workouts into
 *    the next day and quietly skew every per-week figure
 *  - rows it cannot read are counted and reported, never silently dropped
 *
 * Idempotent by construction: a workout's identity is a hash of its start
 * time and title, so re-importing the same file (or a fresh export that
 * overlaps the last one) updates instead of duplicating.
 */
class CsvImport
{
    /** More rows than any human log; fewer than a mistake (a server log, a genome). */
    public const MAX_ROWS = 100_000;

    /** The fields the column-matching screen lets a person point at, in form order. */
    public const MAPPABLE = [
        'start_time', 'exercise_title', 'title', 'set_index', 'set_type',
        'weight', 'reps', 'distance', 'duration_seconds', 'rpe', 'exercise_notes',
    ];

    private const HEADER_ALIASES = [
        'title' => ['title', 'workout_name', 'workout'],
        'start_time' => ['start_time', 'date', 'workout_date'],
        'end_time' => ['end_time'],
        'exercise_title' => ['exercise_title', 'exercise_name', 'exercise'],
        'set_index' => ['set_index', 'set_order', 'set'],
        'set_type' => ['set_type', 'type'],
        'weight_kg' => ['weight_kg'],
        'weight_lbs' => ['weight_lbs', 'weight_lb'],
        // Strong's shape: a unit-less "Weight" column, its unit beside it
        // (Android) or nowhere in the file at all (iPhone).
        'weight' => ['weight'],
        'weight_unit' => ['weight_unit'],
        'reps' => ['reps', 'repetitions'],
        'distance_km' => ['distance_km'],
        'distance_miles' => ['distance_miles', 'distance_mi'],
        'distance' => ['distance'],
        'distance_unit' => ['distance_unit'],
        'duration_seconds' => ['duration_seconds', 'seconds'],
        'rpe' => ['rpe'],
        'superset_id' => ['superset_id'],
        'exercise_notes' => ['exercise_notes', 'notes'],
        'description' => ['description'],
    ];

    /**
     * Named sources, matched by header signature, checked in order. Each may
     * carry extra aliases that would be wrong to honour globally — FitNotes'
     * "Time" is a duration, but on other files a column called Time is a
     * clock time, so the alias only applies once the signature matched.
     */
    private const SOURCES = [
        'hevy' => [
            'signature' => ['exercise_title', 'start_time'],
            'aliases' => [],
        ],
        'strong' => [
            'signature' => ['workout_name', 'exercise_name', 'set_order'],
            'aliases' => [],
        ],
        'jefit' => [
            'signature' => ['mydate', 'ename', 'logs'],
            'aliases' => [
                'start_time' => ['mydate'],
                'exercise_title' => ['ename'],
                'jefit_logs' => ['logs'],
            ],
        ],
        'fitnotes' => [
            'signature' => ['date', 'exercise', 'category'],
            'aliases' => [
                'weight_kg' => ['weight_(kg)'],
                'weight_lbs' => ['weight_(lbs)', 'weight_(lb)'],
                'duration_seconds' => ['time'],
            ],
        ],
    ];

    private string $unitFallback = 'kg';

    public function __construct(private readonly User $user) {}

    /**
     * @param  array{unit?: ?string, map?: array<string, int>}  $options
     * @return array{workouts: int, sets: int, skipped: int, errors: array<int, string>, source: string}
     *
     * @throws ImportException when the file as a whole cannot be understood.
     * @throws UnknownCsvFormat when it is a real CSV whose columns match nothing.
     */
    public function run(string $path, array $options = []): array
    {
        $this->unitFallback = ($options['unit'] ?? null) === 'lbs' ? 'lbs' : 'kg';

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new ImportException(__('app.import.errors.unreadable'));
        }

        try {
            return $this->parse($handle, $options['map'] ?? null);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Best-guess mapping for the column-matching screen: canonical field =>
     * column index, using everything the engine knows about every source.
     *
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    public static function guess(array $headers): array
    {
        $aliases = self::HEADER_ALIASES;

        foreach (self::SOURCES as $source) {
            foreach ($source['aliases'] as $canonical => $extra) {
                $aliases[$canonical] = array_merge($aliases[$canonical] ?? [], $extra);
            }
        }

        return self::mapHeader($headers, $aliases);
    }

    /**
     * @param  resource  $handle
     * @param  ?array<string, int>  $map
     */
    private function parse($handle, ?array $map): array
    {
        $firstLine = fgets($handle);

        if ($firstLine === false) {
            throw new ImportException(__('app.import.errors.not_csv'));
        }

        // Strip the BOM Excel loves to prepend; it otherwise glues itself to
        // the first header name and nothing matches.
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);

        // Sniff the delimiter from the header line. Strong's exports (and any
        // file that passed through European Excel) use semicolons; assuming
        // commas would read the whole header as one unmatchable column.
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = str_getcsv(rtrim($firstLine, "\r\n"), $delimiter);

        if (count($header) < 3) {
            throw new ImportException(__('app.import.errors.not_csv'));
        }

        $source = 'generic';

        if ($map !== null) {
            $source = 'mapped';
            $columns = $map;
        } else {
            $normalized = array_map(fn ($h) => self::normalize((string) $h), $header);
            $aliases = self::HEADER_ALIASES;

            foreach (self::SOURCES as $name => $spec) {
                if (array_diff($spec['signature'], $normalized) === []) {
                    $source = $name;

                    foreach ($spec['aliases'] as $canonical => $extra) {
                        $aliases[$canonical] = array_merge($extra, $aliases[$canonical] ?? []);
                    }

                    break;
                }
            }

            $columns = self::mapHeader($header, $aliases);
        }

        if (! isset($columns['start_time'], $columns['exercise_title'])) {
            // A real CSV that matches nothing is not a dead end: hand the
            // controller what it needs to offer the column-matching screen.
            throw new UnknownCsvFormat(
                headers: array_map(fn ($h) => trim((string) $h), $header),
                preview: $this->previewRows($handle, $delimiter),
                delimiter: $delimiter,
            );
        }

        // First pass: group rows into workouts in memory. Exports are one row
        // per SET, often newest first; grouping must not assume any order.
        $workouts = [];
        $errors = [];
        $skipped = 0;
        $rows = 0;

        while (($row = fgetcsv($handle, null, $delimiter)) !== false) {
            if ($row === [null] || $row === false) {
                continue; // blank line
            }

            if (++$rows > self::MAX_ROWS) {
                throw new ImportException(__('app.import.errors.too_many_rows', ['max' => number_format(self::MAX_ROWS)]));
            }

            $get = fn (string $key) => isset($columns[$key], $row[$columns[$key]])
                ? trim((string) $row[$columns[$key]])
                : '';

            $title = $get('title') !== '' ? $get('title') : 'Workout';
            $rawStart = $get('start_time');
            $exercise = $get('exercise_title');

            if ($rawStart === '' || $exercise === '') {
                $skipped++;

                if (count($errors) < 5) {
                    $errors[] = __('app.import.errors.row_missing', ['row' => $rows + 1]);
                }

                continue;
            }

            $start = $this->parseDate($rawStart);

            if ($start === null) {
                $skipped++;

                if (count($errors) < 5) {
                    $errors[] = __('app.import.errors.bad_date', ['row' => $rows + 1, 'value' => Str::limit($rawStart, 30)]);
                }

                continue;
            }

            $key = $rawStart.'|'.$title;

            $workouts[$key] ??= [
                'title' => $title,
                'start' => $start,
                'end' => $this->parseDate($get('end_time')),
                'description' => $get('description') ?: null,
                'exercises' => [],
            ];

            $workouts[$key]['exercises'][$exercise] ??= [
                'title' => $exercise,
                'superset_id' => $get('superset_id') !== '' ? (int) $get('superset_id') : null,
                'notes' => $get('exercise_notes') ?: null,
                'sets' => [],
            ];

            $bucket = &$workouts[$key]['exercises'][$exercise]['sets'];

            // Jefit packs a whole exercise into one row: logs = "50x10,55x8".
            if ($source === 'jefit') {
                if (! preg_match_all('/([\d.]+)\s*x\s*(\d+)/i', $get('jefit_logs'), $pairs, PREG_SET_ORDER)) {
                    $skipped++;

                    continue;
                }

                foreach ($pairs as $pair) {
                    $bucket[] = [
                        'index' => count($bucket),
                        'type' => 'normal',
                        'weight_kg' => $this->toKg((float) $pair[1]),
                        'reps' => (int) $pair[2],
                        'distance_meters' => null,
                        'duration_seconds' => null,
                        'rpe' => null,
                    ];
                }

                unset($bucket);

                continue;
            }

            $weight = $this->weightKg($get);
            $distance = $this->distanceMeters($get);

            // Strong has no set_type column; it marks warmups by writing the
            // set order as "W" (or "W1"). Read that shape when no explicit
            // type exists, so warmups do not pollute the hard-set counts.
            $rawIndex = $get('set_index');
            $type = $get('set_type') !== ''
                ? $this->setType($get('set_type'))
                : (str_starts_with(mb_strtolower($rawIndex), 'w') ? 'warmup' : 'normal');

            $bucket[] = [
                // The file's own row order is the index. Trusting the set
                // column instead invites collisions — Strong numbers warmups
                // as "W1" alongside a working set "1" — and both apps write
                // rows in the order the sets were done anyway.
                'index' => count($bucket),
                'type' => $type,
                'weight_kg' => $weight,
                'reps' => $get('reps') !== '' ? (int) $get('reps') : null,
                'distance_meters' => $distance !== null ? (int) round($distance) : null,
                'duration_seconds' => $this->durationSeconds($get('duration_seconds')),
                'rpe' => $get('rpe') !== '' ? (float) $get('rpe') : null,
            ];

            unset($bucket);
        }

        if ($workouts === []) {
            throw new ImportException(__('app.import.errors.nothing_found'));
        }

        // Second pass: write. One transaction per workout, not one giant one —
        // a 40 000-row import holding a single transaction open for minutes is
        // how other requests start timing out on locks.
        $templates = $this->templateMap();
        $setCount = 0;

        foreach ($workouts as $w) {
            $setCount += $this->persist($w, $templates);
        }

        return [
            'workouts' => count($workouts),
            'sets' => $setCount,
            'skipped' => $skipped,
            'errors' => $errors,
            'source' => $source,
        ];
    }

    /**
     * Up to three raw data rows, for the column-matching screen's preview.
     *
     * @param  resource  $handle
     * @return array<int, array<int, string>>
     */
    private function previewRows($handle, string $delimiter): array
    {
        $rows = [];

        while (count($rows) < 3 && ($row = fgetcsv($handle, null, $delimiter)) !== false) {
            if ($row === [null]) {
                continue;
            }

            $rows[] = array_map(fn ($v) => Str::limit(trim((string) $v), 40), $row);
        }

        return $rows;
    }

    /** @param array<string, string> $templates */
    private function persist(array $w, array &$templates): int
    {
        $sets = 0;

        DB::transaction(function () use ($w, &$templates, &$sets) {
            $workout = $this->user->workouts()->updateOrCreate(
                // Deterministic identity: the same workout in two exports is
                // the same row, so re-imports and overlapping exports merge.
                ['hevy_id' => 'csv:'.sha1($w['start']->toIso8601String().'|'.$w['title'])],
                [
                    'title' => $w['title'],
                    'description' => $w['description'],
                    'start_time' => $w['start'],
                    'end_time' => $w['end'],
                ],
            );

            $workout->exercises()->delete();

            $index = 0;

            foreach ($w['exercises'] as $ex) {
                $we = $workout->exercises()->create([
                    'index' => $index++,
                    'title' => $ex['title'],
                    'notes' => $ex['notes'],
                    'superset_id' => $ex['superset_id'],
                    'exercise_template_hevy_id' => $this->templateFor($ex['title'], $templates),
                ]);

                $rows = [];

                foreach ($ex['sets'] as $set) {
                    $rows[] = $set + [
                        'workout_exercise_id' => $we->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $we->sets()->insert($rows);
                $sets += count($rows);
            }
        });

        return $sets;
    }

    /**
     * Template id for an exercise title, creating a synthetic one on first
     * sight. When the account ALREADY has an API-synced template with this
     * title, that one is reused — Hevy's own muscle attribution beats keyword
     * inference, and volume filed under one id instead of two keeps the
     * per-exercise history unified for people who later add an API key.
     *
     * @param  array<string, string>  $templates  title(lower) => hevy_id
     */
    private function templateFor(string $title, array &$templates): string
    {
        $key = mb_strtolower($title);

        if (isset($templates[$key])) {
            return $templates[$key];
        }

        [$primary, $secondary] = ExerciseMuscles::resolve($title);

        $id = 'csv:'.Str::slug(Str::limit($title, 60, ''));

        $this->user->exerciseTemplates()->updateOrCreate(
            ['hevy_id' => $id],
            [
                'title' => $title,
                'type' => 'weight_reps',
                'primary_muscle_group' => $primary,
                'secondary_muscle_groups' => $secondary,
                'is_custom' => true,
            ],
        );

        return $templates[$key] = $id;
    }

    /** @return array<string, string> */
    private function templateMap(): array
    {
        return $this->user->exerciseTemplates()
            ->get(['title', 'hevy_id'])
            ->mapWithKeys(fn ($t) => [mb_strtolower((string) $t->title) => $t->hevy_id])
            ->all();
    }

    /**
     * @param  array<int, string>  $header
     * @param  array<string, array<int, string>>  $aliases
     * @return array<string, int> canonical name => column index
     */
    private static function mapHeader(array $header, array $aliases): array
    {
        $map = [];

        foreach ($header as $i => $name) {
            $name = self::normalize((string) $name);

            foreach ($aliases as $canonical => $names) {
                if (in_array($name, $names, true) && ! isset($map[$canonical])) {
                    $map[$canonical] = $i;
                }
            }
        }

        return $map;
    }

    /** "Workout Name" and "workout_name" are the same header wearing different apps' clothes. */
    private static function normalize(string $name): string
    {
        return str_replace(' ', '_', mb_strtolower(trim($name)));
    }

    private function parseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        $tz = $this->user->resolvedTimezone();

        // Hevy's own export writes "27 Jul 2025, 18:30". The rest cover other
        // regional settings and hand-edited files.
        foreach (['d M Y, H:i', 'j M Y, H:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i', 'm/d/Y H:i'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value, $tz)->utc();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value, $tz)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param  callable(string): string  $get */
    private function weightKg(callable $get): ?float
    {
        if ($get('weight_kg') !== '') {
            return (float) $get('weight_kg');
        }

        if ($get('weight_lbs') !== '') {
            return round((float) $get('weight_lbs') * 0.45359237, 2);
        }

        if ($get('weight') !== '') {
            $value = (float) $get('weight');

            // A unit column decides. Absent one, the unit the athlete picked
            // on the form — Strong's iPhone export carries no unit at all,
            // and guessing would corrupt every number by 2.2× for someone.
            $unit = $get('weight_unit') !== '' ? $get('weight_unit') : $this->unitFallback;

            return str_starts_with(mb_strtolower($unit), 'lb')
                ? round($value * 0.45359237, 2)
                : $value;
        }

        return null;
    }

    /** File-unit weight -> kg, honouring the form's unit choice. */
    private function toKg(float $value): float
    {
        return $this->unitFallback === 'lbs' ? round($value * 0.45359237, 2) : $value;
    }

    /** Accepts plain seconds and FitNotes' "0:30:00" clock shape. */
    private function durationSeconds(string $raw): ?int
    {
        if ($raw === '') {
            return null;
        }

        if (! str_contains($raw, ':')) {
            return (int) $raw;
        }

        $parts = array_map(intval(...), explode(':', $raw));

        return match (count($parts)) {
            2 => $parts[0] * 60 + $parts[1],
            3 => $parts[0] * 3600 + $parts[1] * 60 + $parts[2],
            default => null,
        };
    }

    /** @param  callable(string): string  $get */
    private function distanceMeters(callable $get): ?int
    {
        $km = null;

        if ($get('distance_km') !== '') {
            $km = (float) $get('distance_km');
        } elseif ($get('distance_miles') !== '') {
            $km = (float) $get('distance_miles') * 1.609344;
        } elseif ($get('distance') !== '') {
            $value = (float) $get('distance');
            $unit = mb_strtolower($get('distance_unit'));

            $km = match (true) {
                str_starts_with($unit, 'mi') => $value * 1.609344,
                $unit === 'm' || str_starts_with($unit, 'meter') || str_starts_with($unit, 'metre') => $value / 1000,
                default => $value,
            };
        }

        return $km !== null ? (int) round($km * 1000) : null;
    }

    private function setType(string $raw): string
    {
        return match (mb_strtolower($raw)) {
            'warmup', 'warm-up', 'w' => 'warmup',
            'failure', 'f' => 'failure',
            'dropset', 'drop', 'd' => 'dropset',
            default => 'normal',
        };
    }
}
