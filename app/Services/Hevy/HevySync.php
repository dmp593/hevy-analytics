<?php

namespace App\Services\Hevy;

use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class HevySync
{
    public function __construct(private readonly User $user)
    {
        //
    }

    public function client(): HevyClient
    {
        return new HevyClient($this->user->hevy_api_key);
    }

    /**
     * Run a sync. Uses incremental workout events when possible, otherwise full.
     *
     * @return array<string,int> counts
     */
    public function run(bool $force = false): array
    {
        $incremental = ! $force && $this->user->hevy_last_synced_at !== null;

        // Take the watermark BEFORE fetching. Stamping it on completion means
        // anything the user logs while the sync is running falls between the old
        // and new watermark and is never picked up by a later incremental run.
        $startedAt = now();

        $log = SyncLog::create([
            'user_id' => $this->user->id,
            'type' => $incremental ? 'incremental' : 'full',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $counts = ['templates' => 0, 'routine_folders' => 0, 'routines' => 0, 'workouts' => 0, 'body_measurements' => 0];

        try {
            $counts['templates'] = $this->syncExerciseTemplates();
            $counts['routine_folders'] = $this->syncRoutineFolders();
            $counts['routines'] = $this->syncRoutines();
            $counts['workouts'] = $incremental ? $this->syncWorkoutEvents() : $this->syncAllWorkouts();
            $counts['body_measurements'] = $this->syncBodyMeasurements();

            $this->user->forceFill(['hevy_last_synced_at' => $startedAt])->save();

            $log->update(['status' => 'success', 'counts' => $counts, 'finished_at' => now()]);
        } catch (Throwable $e) {
            $log->update(['status' => 'failed', 'counts' => $counts, 'error' => $e->getMessage(), 'finished_at' => now()]);
            throw $e;
        }

        return $counts;
    }

    private function syncExerciseTemplates(): int
    {
        $client = $this->client();
        $page = 1;
        $count = 0;
        do {
            $data = $client->exerciseTemplates($page, 100);
            foreach ($data['exercise_templates'] ?? [] as $t) {
                $this->user->exerciseTemplates()->updateOrCreate(
                    ['hevy_id' => $t['id']],
                    [
                        'title' => $t['title'] ?? null,
                        'type' => $t['type'] ?? null,
                        'primary_muscle_group' => $t['primary_muscle_group'] ?? null,
                        'secondary_muscle_groups' => $t['secondary_muscle_groups'] ?? [],
                        'equipment' => $t['equipment'] ?? null,
                        'is_custom' => $t['is_custom'] ?? false,
                    ]
                );
                $count++;
            }
            $pageCount = $data['page_count'] ?? 1;
        } while ($page++ < $pageCount);

        return $count;
    }

    private function syncRoutineFolders(): int
    {
        $client = $this->client();
        $page = 1;
        $count = 0;
        do {
            $data = $client->routineFolders($page, 10);
            foreach ($data['routine_folders'] ?? [] as $f) {
                $this->user->routineFolders()->updateOrCreate(
                    ['hevy_id' => (string) ($f['id'] ?? '')],
                    ['title' => $f['title'] ?? 'Folder', 'index' => $f['index'] ?? null]
                );
                $count++;
            }
            $pageCount = $data['page_count'] ?? 1;
        } while ($page++ < $pageCount);

        return $count;
    }

    private function syncRoutines(): int
    {
        $client = $this->client();
        $page = 1;
        $count = 0;
        do {
            $data = $client->routines($page, 10);
            foreach ($data['routines'] ?? [] as $r) {
                $routine = $this->user->routines()->updateOrCreate(
                    ['hevy_id' => $r['id']],
                    [
                        'title' => $r['title'] ?? 'Routine',
                        'folder_hevy_id' => isset($r['folder_id']) ? (string) $r['folder_id'] : null,
                        'notes' => $r['notes'] ?? null,
                        'hevy_created_at' => $this->parseDate($r['created_at'] ?? null),
                        'hevy_updated_at' => $this->parseDate($r['updated_at'] ?? null),
                    ]
                );

                $routine->exercises()->delete();
                foreach ($r['exercises'] ?? [] as $ex) {
                    $routine->exercises()->create([
                        'index' => $ex['index'] ?? 0,
                        'title' => $ex['title'] ?? null,
                        'exercise_template_hevy_id' => $ex['exercise_template_id'] ?? null,
                        'superset_id' => $ex['supersets_id'] ?? $ex['superset_id'] ?? null,
                        'rest_seconds' => is_numeric($ex['rest_seconds'] ?? null) ? (int) $ex['rest_seconds'] : null,
                        'notes' => $ex['notes'] ?? null,
                        'sets' => $ex['sets'] ?? [],
                    ]);
                }
                $count++;
            }
            $pageCount = $data['page_count'] ?? 1;
        } while ($page++ < $pageCount);

        return $count;
    }

    private function syncAllWorkouts(): int
    {
        $client = $this->client();
        $page = 1;
        $count = 0;
        do {
            $data = $client->workouts($page, 10);
            foreach ($data['workouts'] ?? [] as $w) {
                $this->upsertWorkout($w);
                $count++;
            }
            $pageCount = $data['page_count'] ?? 1;
        } while ($page++ < $pageCount);

        return $count;
    }

    private function syncWorkoutEvents(): int
    {
        $client = $this->client();
        $since = optional($this->user->hevy_last_synced_at)->toIso8601String() ?? now()->subYears(5)->toIso8601String();
        $page = 1;
        $count = 0;
        do {
            $data = $client->workoutEvents($since, $page, 10);
            foreach ($data['events'] ?? [] as $event) {
                $type = $event['type'] ?? 'updated';
                if ($type === 'deleted') {
                    $id = $event['id'] ?? ($event['workout']['id'] ?? null);
                    if ($id) {
                        $this->user->workouts()->where('hevy_id', $id)->delete();
                    }
                } elseif (isset($event['workout'])) {
                    $this->upsertWorkout($event['workout']);
                }
                $count++;
            }
            $pageCount = $data['page_count'] ?? 1;
        } while ($page++ < $pageCount);

        return $count;
    }

    private function upsertWorkout(array $w): void
    {
        DB::transaction(function () use ($w) {
            $workout = $this->user->workouts()->updateOrCreate(
                ['hevy_id' => $w['id']],
                [
                    'title' => $w['title'] ?? null,
                    'routine_hevy_id' => $w['routine_id'] ?? null,
                    'description' => $w['description'] ?? null,
                    'start_time' => $this->parseDate($w['start_time'] ?? null),
                    'end_time' => $this->parseDate($w['end_time'] ?? null),
                    'hevy_created_at' => $this->parseDate($w['created_at'] ?? null),
                    'hevy_updated_at' => $this->parseDate($w['updated_at'] ?? null),
                ]
            );

            $workout->exercises()->delete();
            foreach ($w['exercises'] ?? [] as $ex) {
                $we = $workout->exercises()->create([
                    'index' => $ex['index'] ?? 0,
                    'title' => $ex['title'] ?? null,
                    'notes' => $ex['notes'] ?? null,
                    'exercise_template_hevy_id' => $ex['exercise_template_id'] ?? null,
                    'superset_id' => $ex['supersets_id'] ?? $ex['superset_id'] ?? null,
                ]);

                $rows = [];
                foreach ($ex['sets'] ?? [] as $set) {
                    $rows[] = [
                        'workout_exercise_id' => $we->id,
                        'index' => $set['index'] ?? 0,
                        'type' => $set['type'] ?? 'normal',
                        'weight_kg' => $set['weight_kg'] ?? null,
                        'reps' => $set['reps'] ?? null,
                        'distance_meters' => $set['distance_meters'] ?? null,
                        'duration_seconds' => $set['duration_seconds'] ?? null,
                        'rpe' => $set['rpe'] ?? null,
                        'custom_metric' => $set['custom_metric'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                if ($rows) {
                    $we->sets()->insert($rows);
                }
            }
        });
    }

    private function syncBodyMeasurements(): int
    {
        $client = $this->client();
        $page = 1;
        $count = 0;
        do {
            $data = $client->bodyMeasurements($page, 10);
            foreach ($data['body_measurements'] ?? [] as $m) {
                $record = $this->user->bodyMeasurements()->whereDate('date', $m['date'])->first()
                    ?? $this->user->bodyMeasurements()->make(['date' => $m['date']]);
                $record->fill([
                    'hevy_id' => isset($m['id']) ? (string) $m['id'] : null,
                    'weight_kg' => $m['weight_kg'] ?? null,
                    'lean_mass_kg' => $m['lean_mass_kg'] ?? null,
                    'fat_percent' => $m['fat_percent'] ?? null,
                    'neck_cm' => $m['neck_cm'] ?? null,
                    'shoulder_cm' => $m['shoulder_cm'] ?? null,
                    'chest_cm' => $m['chest_cm'] ?? null,
                    'left_bicep_cm' => $m['left_bicep_cm'] ?? null,
                    'right_bicep_cm' => $m['right_bicep_cm'] ?? null,
                    'left_forearm_cm' => $m['left_forearm_cm'] ?? null,
                    'right_forearm_cm' => $m['right_forearm_cm'] ?? null,
                    'abdomen' => $m['abdomen'] ?? null,
                    'waist' => $m['waist'] ?? null,
                    'hips' => $m['hips'] ?? null,
                    'left_thigh' => $m['left_thigh'] ?? null,
                    'right_thigh' => $m['right_thigh'] ?? null,
                    'left_calf' => $m['left_calf'] ?? null,
                    'right_calf' => $m['right_calf'] ?? null,
                ])->save();
                $count++;
            }
            $pageCount = $data['page_count'] ?? 1;
        } while ($page++ < $pageCount);

        return $count;
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
