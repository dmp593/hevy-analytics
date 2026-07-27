<?php

namespace App\Services\Import;

use App\Models\User;
use App\Support\ExerciseMuscles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports the CSV that Hevy's own "Export data" produces.
 *
 * This is the door for people the API cannot serve: Hevy issues API keys to
 * Pro subscribers only, but the CSV export is available to every account. It
 * is also the template for every future source (Strong, Jefit — see
 * docs/DATA-SOURCES.md), which is why parsing is deliberately tolerant:
 *
 *  - weight_kg or weight_lbs, whichever the account's units produced
 *  - several date shapes, interpreted in the ATHLETE'S timezone — a CSV has
 *    no offset, and parsing "18:30" as UTC would shift evening workouts into
 *    the next day and quietly skew every per-week figure
 *  - rows it cannot read are counted and reported, never silently dropped
 *
 * Idempotent by construction: a workout's identity is a hash of its start
 * time and title, so re-importing the same file (or a fresh export that
 * overlaps the last one) updates instead of duplicating.
 */
class HevyCsvImport
{
    /** More rows than any human log; fewer than a mistake (a server log, a genome). */
    public const MAX_ROWS = 100_000;

    private const HEADER_ALIASES = [
        'title' => ['title', 'workout_name', 'workout'],
        'start_time' => ['start_time', 'date', 'workout_date'],
        'end_time' => ['end_time'],
        'exercise_title' => ['exercise_title', 'exercise_name', 'exercise'],
        'set_index' => ['set_index', 'set_order', 'set'],
        'set_type' => ['set_type', 'type'],
        'weight_kg' => ['weight_kg'],
        'weight_lbs' => ['weight_lbs', 'weight_lb'],
        'reps' => ['reps', 'repetitions'],
        'distance_km' => ['distance_km'],
        'distance_miles' => ['distance_miles', 'distance_mi'],
        'duration_seconds' => ['duration_seconds', 'seconds'],
        'rpe' => ['rpe'],
        'superset_id' => ['superset_id'],
        'exercise_notes' => ['exercise_notes', 'notes'],
        'description' => ['description'],
    ];

    public function __construct(private readonly User $user) {}

    /**
     * @return array{workouts: int, sets: int, skipped: int, errors: array<int, string>}
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
        $header = fgetcsv($handle);

        if ($header === false || count($header) < 3) {
            throw new ImportException(__('app.import.errors.not_csv'));
        }

        // Strip the BOM Excel loves to prepend; it otherwise glues itself to
        // the first header name and nothing matches.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

        $columns = $this->mapHeader($header);

        if (! isset($columns['title'], $columns['start_time'], $columns['exercise_title'])) {
            throw new ImportException(__('app.import.errors.missing_columns', [
                'columns' => 'title, start_time, exercise_title',
            ]));
        }

        // First pass: group rows into workouts in memory. Hevy exports one row
        // per SET, newest workout first; grouping must not assume any order.
        $workouts = [];
        $errors = [];
        $skipped = 0;
        $rows = 0;

        while (($row = fgetcsv($handle)) !== false) {
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

            $weight = $get('weight_kg') !== '' ? (float) $get('weight_kg')
                : ($get('weight_lbs') !== '' ? round((float) $get('weight_lbs') * 0.45359237, 2) : null);

            $distance = $get('distance_km') !== '' ? (float) $get('distance_km') * 1000
                : ($get('distance_miles') !== '' ? (float) $get('distance_miles') * 1609.344 : null);

            $workouts[$key]['exercises'][$exercise]['sets'][] = [
                'index' => $get('set_index') !== '' ? (int) $get('set_index') : count($workouts[$key]['exercises'][$exercise]['sets']),
                'type' => $this->setType($get('set_type')),
                'weight_kg' => $weight,
                'reps' => $get('reps') !== '' ? (int) $get('reps') : null,
                'distance_meters' => $distance !== null ? (int) round($distance) : null,
                'duration_seconds' => $get('duration_seconds') !== '' ? (int) $get('duration_seconds') : null,
                'rpe' => $get('rpe') !== '' ? (float) $get('rpe') : null,
            ];
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
        ];
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

    /** @return array<string, int> canonical name => column index */
    private function mapHeader(array $header): array
    {
        $map = [];

        foreach ($header as $i => $name) {
            $name = mb_strtolower(trim((string) $name));

            foreach (self::HEADER_ALIASES as $canonical => $aliases) {
                if (in_array($name, $aliases, true) && ! isset($map[$canonical])) {
                    $map[$canonical] = $i;
                }
            }
        }

        return $map;
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
