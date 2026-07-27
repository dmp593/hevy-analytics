<?php

namespace App\Services\Hevy;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin, typed wrapper around the Hevy public API (https://api.hevyapp.com).
 * Handles auth header, pagination limits (workouts pageSize max 10) and retries.
 */
class HevyClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly ?string $baseUrl = null,
    ) {}

    /** Read requests. Safe to retry — GET has no side effects. */
    private function request(): PendingRequest
    {
        return $this->base()->retry(3, 500, throw: false);
    }

    /**
     * Write requests.
     *
     * POST is NOT idempotent: a request that reached Hevy and succeeded but
     * whose response was lost to a timeout would, on retry, create a SECOND
     * workout in the user's real training log. That is data we do not own and
     * cannot clean up, so creates get exactly one attempt.
     *
     * PUT against a known id is idempotent by HTTP semantics — the same payload
     * to the same resource converges on the same state — so updates may retry.
     *
     * $idempotencyKey is sent as a best-effort header. If Hevy honours it we get
     * server-side protection too; if it ignores the header, the retry policy
     * above is what actually keeps writes safe.
     */
    private function writeRequest(bool $idempotent, ?string $idempotencyKey = null): PendingRequest
    {
        $request = $this->base();

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        return $idempotent ? $request->retry(3, 500, throw: false) : $request;
    }

    private function base(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl ?? config('services.hevy.base_url'), '/'))
            ->withHeaders(['api-key' => $this->apiKey, 'Accept' => 'application/json'])
            ->timeout(30);
    }

    public function userInfo(): array
    {
        return $this->request()->get('/v1/user/info')->throw()->json('data', []);
    }

    public function workoutCount(): int
    {
        return (int) $this->request()->get('/v1/workouts/count')->throw()->json('workout_count', 0);
    }

    /** @return array{page:int, page_count:int, workouts:array} */
    public function workouts(int $page = 1, int $pageSize = 10): array
    {
        return $this->request()->get('/v1/workouts', [
            'page' => $page,
            'pageSize' => min($pageSize, 10),
        ])->throw()->json();
    }

    public function workout(string $id): array
    {
        return $this->request()->get("/v1/workouts/{$id}")->throw()->json();
    }

    /** Workout events (updates/deletes) since a date, for incremental sync. */
    public function workoutEvents(string $since, int $page = 1, int $pageSize = 10): array
    {
        return $this->request()->get('/v1/workouts/events', [
            'since' => $since,
            'page' => $page,
            'pageSize' => min($pageSize, 10),
        ])->throw()->json();
    }

    public function exerciseTemplates(int $page = 1, int $pageSize = 100): array
    {
        return $this->request()->get('/v1/exercise_templates', [
            'page' => $page,
            'pageSize' => min($pageSize, 100),
        ])->throw()->json();
    }

    public function routines(int $page = 1, int $pageSize = 10): array
    {
        return $this->request()->get('/v1/routines', [
            'page' => $page,
            'pageSize' => min($pageSize, 10),
        ])->throw()->json();
    }

    public function routineFolders(int $page = 1, int $pageSize = 10): array
    {
        return $this->request()->get('/v1/routine_folders', [
            'page' => $page,
            'pageSize' => min($pageSize, 10),
        ])->throw()->json();
    }

    public function bodyMeasurements(int $page = 1, int $pageSize = 10): array
    {
        return $this->request()->get('/v1/body_measurements', [
            'page' => $page,
            'pageSize' => min($pageSize, 10),
        ])->throw()->json();
    }

    // ---- Write operations ----

    public function createWorkout(array $payload, ?string $idempotencyKey = null): Response
    {
        return $this->writeRequest(false, $idempotencyKey)->post('/v1/workouts', $payload);
    }

    public function updateWorkout(string $id, array $payload, ?string $idempotencyKey = null): Response
    {
        return $this->writeRequest(true, $idempotencyKey)->put("/v1/workouts/{$id}", $payload);
    }

    public function createRoutine(array $payload, ?string $idempotencyKey = null): Response
    {
        return $this->writeRequest(false, $idempotencyKey)->post('/v1/routines', $payload);
    }

    public function updateRoutine(string $id, array $payload, ?string $idempotencyKey = null): Response
    {
        return $this->writeRequest(true, $idempotencyKey)->put("/v1/routines/{$id}", $payload);
    }

    public function createRoutineFolder(array $payload, ?string $idempotencyKey = null): Response
    {
        return $this->writeRequest(false, $idempotencyKey)->post('/v1/routine_folders', $payload);
    }

    public function createExerciseTemplate(array $payload, ?string $idempotencyKey = null): Response
    {
        return $this->writeRequest(false, $idempotencyKey)->post('/v1/exercise_templates', $payload);
    }
}
