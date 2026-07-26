<?php

namespace App\Services\Hevy;

use App\Models\User;
use App\Models\WriteOperation;
use Illuminate\Support\Str;

/**
 * Handles authenticated write-back to Hevy with a confirm + audit workflow.
 * Every mutation is first staged as a WriteOperation (status=pending), then
 * executed on confirmation, recording the response for audit/revert.
 */
class HevyWriter
{
    public function __construct(private readonly User $user) {}

    private function client(): HevyClient
    {
        return new HevyClient($this->user->hevy_api_key);
    }

    /** Stage an operation for confirmation. */
    public function stage(string $operation, string $method, string $endpoint, array $payload): WriteOperation
    {
        return $this->user->writeOperations()->create([
            'operation' => $operation,
            'method' => strtoupper($method),
            'endpoint' => $endpoint,
            'payload' => $payload,
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    /** Execute a previously-staged (or fresh) operation. */
    public function execute(WriteOperation $op): WriteOperation
    {
        if (! in_array($op->status, ['pending', 'confirmed', 'failed'], true)) {
            return $op;
        }

        $client = $this->client();
        $payload = $op->payload ?? [];
        $targetId = (string) ($payload['_target_id'] ?? '');
        unset($payload['_target_id']);

        $response = match ($op->operation) {
            'workout.create' => $client->createWorkout($payload),
            'workout.update' => $client->updateWorkout($targetId, $payload),
            'routine.create' => $client->createRoutine($payload),
            'routine.update' => $client->updateRoutine($targetId, $payload),
            'routine_folder.create' => $client->createRoutineFolder($payload),
            'exercise_template.create' => $client->createExerciseTemplate($payload),
            default => null,
        };

        if ($response === null) {
            $op->update(['status' => 'failed', 'response' => ['error' => 'Unknown operation']]);

            return $op;
        }

        $op->update([
            'status' => $response->successful() ? 'success' : 'failed',
            'status_code' => $response->status(),
            'response' => $response->json() ?? ['body' => $response->body()],
        ]);

        // Re-sync so local state reflects the change.
        if ($response->successful()) {
            try {
                (new HevySync($this->user))->run();
            } catch (\Throwable) {
                // sync failure shouldn't fail the write; user can resync manually
            }
        }

        return $op;
    }
}
