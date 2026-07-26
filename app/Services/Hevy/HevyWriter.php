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

    /**
     * Execute a previously-staged (or fresh) operation.
     *
     * Claims the operation before calling out. Without that, two concurrent
     * confirmations of the same staged write — a double-clicked button, a
     * retried request — both saw status "pending" and both fired, creating
     * duplicate records in the user's real Hevy account. The idempotency key
     * generated at stage time is now actually sent.
     */
    public function execute(WriteOperation $op): WriteOperation
    {
        if (! in_array($op->status, ['pending', 'confirmed', 'failed'], true)) {
            return $op;
        }

        $claimed = WriteOperation::query()
            ->whereKey($op->getKey())
            ->whereIn('status', ['pending', 'confirmed', 'failed'])
            ->update(['status' => 'running']);

        if ($claimed === 0) {
            return $op->refresh();
        }

        $op->refresh();

        $client = $this->client();
        $payload = $op->payload ?? [];
        $targetId = (string) ($payload['_target_id'] ?? '');
        unset($payload['_target_id']);

        $key = $op->idempotency_key;

        $response = match ($op->operation) {
            'workout.create' => $client->createWorkout($payload, $key),
            'workout.update' => $client->updateWorkout($targetId, $payload, $key),
            'routine.create' => $client->createRoutine($payload, $key),
            'routine.update' => $client->updateRoutine($targetId, $payload, $key),
            'routine_folder.create' => $client->createRoutineFolder($payload, $key),
            'exercise_template.create' => $client->createExerciseTemplate($payload, $key),
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
