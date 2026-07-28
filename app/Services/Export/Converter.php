<?php

namespace App\Services\Export;

use App\Models\User;
use App\Services\Import\CsvImport;
use App\Support\ExerciseMuscles;
use Illuminate\Support\Carbon;

/**
 * Writes training history in another app's CSV dialect, so switching
 * platforms does not mean abandoning years of data.
 *
 * The reading half already exists: CsvImport::read() normalises every dialect
 * (and the account's own workouts normalise the same way here). This class is
 * only the reverse edge — one writer per target, all fed from the identical
 * structure, so a conversion can also be round-tripped through our own parser
 * in tests.
 *
 * Weights are written in kg everywhere a unit can be stated (Strong gets an
 * explicit "kg" column, FitNotes a "(kg)" header). Jefit's format has no unit
 * at all — the page warns that the destination app must be set to kg.
 *
 * Honesty notes that shaped this file: the free JSON export (GDPR) is NOT
 * this feature and stays free; this is a paid convenience. Exercise names
 * pass through unchanged — the destination app will create custom exercises
 * rather than link its own catalogue. Jefit is labelled beta until a real
 * device confirms its importer's tolerance.
 */
class Converter
{
    public const TARGETS = ['hevy', 'strong', 'fitnotes', 'jefit'];

    public function __construct(private readonly User $user) {}

    /**
     * @return array{workouts: array<int, array>, skipped: int, source: string}
     */
    public function fromFile(string $path, array $options = []): array
    {
        return (new CsvImport($this->user))->read($path, $options);
    }

    /**
     * @return array{workouts: array<int, array>, skipped: int, source: string}
     */
    public function fromAccount(?Carbon $from = null, ?Carbon $to = null): array
    {
        $workouts = $this->user->workouts()
            ->with([
                'exercises' => fn ($q) => $q->orderBy('index'),
                'exercises.sets' => fn ($q) => $q->orderBy('index'),
            ])
            ->when($from, fn ($q) => $q->where('start_time', '>=', $from))
            ->when($to, fn ($q) => $q->where('start_time', '<=', $to->copy()->endOfDay()))
            ->orderBy('start_time')
            ->get()
            ->map(fn ($w) => [
                'title' => $w->title ?: 'Workout',
                'start' => $w->start_time,
                'end' => $w->end_time,
                'description' => $w->description,
                'exercises' => $w->exercises->mapWithKeys(fn ($ex) => [$ex->id => [
                    'title' => $ex->title,
                    'superset_id' => $ex->superset_id,
                    'notes' => $ex->notes,
                    'sets' => $ex->sets->map(fn ($s) => [
                        'index' => (int) $s->index,
                        'type' => $s->type ?: 'normal',
                        'weight_kg' => $s->weight_kg !== null ? (float) $s->weight_kg : null,
                        'reps' => $s->reps !== null ? (int) $s->reps : null,
                        'distance_meters' => $s->distance_meters !== null ? (int) $s->distance_meters : null,
                        'duration_seconds' => $s->duration_seconds !== null ? (int) $s->duration_seconds : null,
                        'rpe' => $s->rpe !== null ? (float) $s->rpe : null,
                    ])->all(),
                ]])->all(),
            ])
            ->values()->all();

        return ['workouts' => $workouts, 'skipped' => 0, 'source' => 'account'];
    }

    /** @param array<int, array> $workouts */
    public function write(array $workouts, string $target): string
    {
        return match ($target) {
            'hevy' => $this->writeHevy($workouts),
            'strong' => $this->writeStrong($workouts),
            'fitnotes' => $this->writeFitNotes($workouts),
            'jefit' => $this->writeJefit($workouts),
        };
    }

    public function filename(string $target): string
    {
        return 'converted-for-'.$target.'-'.now()->format('Y-m-d').'.csv';
    }

    /* -----------------------------------------------------------------
     | Writers. Each receives the same normalised structure; the only
     | thing that varies is which subset the dialect can say out loud.
     */

    private function writeHevy(array $workouts): string
    {
        $tz = $this->user->resolvedTimezone();

        return $this->csv(
            ['title', 'start_time', 'end_time', 'description', 'exercise_title', 'superset_id', 'exercise_notes', 'set_index', 'set_type', 'weight_kg', 'reps', 'distance_km', 'duration_seconds', 'rpe'],
            function (callable $row) use ($workouts, $tz) {
                foreach ($workouts as $w) {
                    $start = $w['start']->copy()->setTimezone($tz)->format('d M Y, H:i');
                    $end = $w['end']?->copy()->setTimezone($tz)->format('d M Y, H:i') ?? '';

                    foreach ($w['exercises'] as $ex) {
                        foreach ($ex['sets'] as $set) {
                            $row([
                                $w['title'], $start, $end, $w['description'] ?? '',
                                $ex['title'], $ex['superset_id'] ?? '', $ex['notes'] ?? '',
                                $set['index'], $set['type'],
                                $this->num($set['weight_kg']),
                                $set['reps'] ?? '',
                                $set['distance_meters'] !== null ? $this->num($set['distance_meters'] / 1000) : '',
                                $set['duration_seconds'] ?? '',
                                $this->num($set['rpe']),
                            ]);
                        }
                    }
                }
            },
        );
    }

    private function writeStrong(array $workouts): string
    {
        $tz = $this->user->resolvedTimezone();

        return $this->csv(
            ['Date', 'Workout Name', 'Exercise Name', 'Set Order', 'Weight', 'Weight Unit', 'Reps', 'RPE', 'Distance', 'Distance Unit', 'Seconds', 'Notes', 'Workout Notes'],
            function (callable $row) use ($workouts, $tz) {
                foreach ($workouts as $w) {
                    $date = $w['start']->copy()->setTimezone($tz)->format('Y-m-d H:i:s');

                    foreach ($w['exercises'] as $ex) {
                        $working = 0;
                        $warmups = 0;

                        foreach ($ex['sets'] as $set) {
                            // Strong's only set-type marker: warmups become a
                            // "W"-prefixed order, exactly as its own exports.
                            $order = $set['type'] === 'warmup' ? 'W'.(++$warmups) : (string) (++$working);

                            $row([
                                $date, $w['title'], $ex['title'], $order,
                                $this->num($set['weight_kg']),
                                $set['weight_kg'] !== null ? 'kg' : '',
                                $set['reps'] ?? '',
                                $this->num($set['rpe']),
                                $set['distance_meters'] ?? '',
                                $set['distance_meters'] !== null ? 'm' : '',
                                $set['duration_seconds'] ?? '',
                                $ex['notes'] ?? '',
                                $w['description'] ?? '',
                            ]);
                        }
                    }
                }
            },
        );
    }

    private function writeFitNotes(array $workouts): string
    {
        $tz = $this->user->resolvedTimezone();

        return $this->csv(
            ['Date', 'Exercise', 'Category', 'Weight (kg)', 'Reps', 'Distance', 'Distance Unit', 'Time'],
            function (callable $row) use ($workouts, $tz) {
                foreach ($workouts as $w) {
                    $date = $w['start']->copy()->setTimezone($tz)->format('Y-m-d');

                    foreach ($w['exercises'] as $ex) {
                        $category = $this->bucket($ex['title']);

                        foreach ($ex['sets'] as $set) {
                            $row([
                                $date, $ex['title'], $category,
                                $this->num($set['weight_kg']),
                                $set['reps'] ?? '',
                                $set['distance_meters'] !== null ? $this->num($set['distance_meters'] / 1000) : '',
                                $set['distance_meters'] !== null ? 'km' : '',
                                $set['duration_seconds'] !== null ? gmdate('G:i:s', $set['duration_seconds']) : '',
                            ]);
                        }
                    }
                }
            },
        );
    }

    private function writeJefit(array $workouts): string
    {
        $tz = $this->user->resolvedTimezone();

        return $this->csv(
            ['_id', 'mydate', 'ename', 'logs', 'bodypart'],
            function (callable $row) use ($workouts, $tz) {
                $id = 0;

                foreach ($workouts as $w) {
                    $date = $w['start']->copy()->setTimezone($tz)->format('Y-m-d');

                    foreach ($w['exercises'] as $ex) {
                        // Jefit packs an exercise into one "50x10,55x8" cell.
                        // Sets without weight AND reps (pure cardio) cannot be
                        // said in this dialect at all — the manifest counts them.
                        $logs = collect($ex['sets'])
                            ->filter(fn ($s) => $s['weight_kg'] !== null || $s['reps'] !== null)
                            ->map(fn ($s) => $this->num($s['weight_kg'] ?? 0).'x'.($s['reps'] ?? 0))
                            ->implode(',');

                        if ($logs === '') {
                            continue;
                        }

                        $row([++$id, $date, $ex['title'], $logs, $this->bucket($ex['title'])]);
                    }
                }
            },
        );
    }

    /* ----------------------------------------------------------------- */

    /**
     * Our muscle groups folded into the coarse category both FitNotes and
     * Jefit think in. Unknown/cardio titles land in Cardio.
     */
    private function bucket(string $exerciseTitle): string
    {
        [$primary] = ExerciseMuscles::resolve($exerciseTitle);

        return match ($primary) {
            'chest' => 'Chest',
            'lats', 'upper_back', 'traps', 'lower_back' => 'Back',
            'shoulders', 'neck' => 'Shoulders',
            'biceps' => 'Biceps',
            'triceps' => 'Triceps',
            'forearms' => 'Forearms',
            'quadriceps', 'hamstrings', 'glutes', 'calves', 'abductors', 'adductors' => 'Legs',
            'abdominals' => 'Abs',
            default => 'Cardio',
        };
    }

    /** Floats without trailing zeros; null and '' pass through empty. */
    private function num(null|int|float $value): string
    {
        if ($value === null) {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /** @param  callable(callable(array): void): void  $body */
    private function csv(array $header, callable $body): string
    {
        $handle = fopen('php://temp', 'r+b');

        fputcsv($handle, $header, ',', '"', '\\');

        $body(function (array $fields) use ($handle) {
            fputcsv($handle, $fields, ',', '"', '\\');
        });

        rewind($handle);
        $out = stream_get_contents($handle);
        fclose($handle);

        return $out;
    }
}
