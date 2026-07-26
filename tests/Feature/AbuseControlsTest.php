<?php

namespace Tests\Feature;

use App\Jobs\SyncHevyJob;
use App\Models\User;
use App\Services\AI\AiQuota;
use App\Services\Hevy\HevyWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards on the things that cost real money or touch data we do not own.
 */
class AbuseControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_users_cannot_reach_the_app(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_verified_users_can_reach_the_app(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_ai_generation_is_refused_once_the_monthly_quota_is_spent(): void
    {
        config(['services.deepseek.key' => 'test-key', 'services.ai.monthly_limit_per_user' => 2]);

        $user = User::factory()->create();
        $user->forceFill(['hevy_api_key' => 'k'])->save();

        foreach (range(1, 2) as $i) {
            $user->aiAnalyses()->create([
                'scope' => 'deep_analysis',
                'params' => [],
                'data_hash' => 'hash-'.$i,
                'prompt' => 'p',
                'response' => 'r',
                'model' => 'test',
            ]);
        }

        $quota = new AiQuota($user);
        $this->assertSame(0, $quota->remaining());
        $this->assertFalse($quota->allows());

        // No outbound call may be made once the allowance is gone.
        Http::fake();
        $this->actingAs($user)->post('/ai/generate')->assertRedirect(route('ai'));
        Http::assertNothingSent();
    }

    public function test_global_ceiling_stops_generation_for_everyone(): void
    {
        config(['services.ai.monthly_limit_global' => 1]);

        $first = User::factory()->create();
        $first->aiAnalyses()->create([
            'scope' => 'deep_analysis', 'params' => [], 'data_hash' => 'h',
            'prompt' => 'p', 'response' => 'r', 'model' => 'test',
        ]);

        $second = User::factory()->create();

        $this->assertTrue(AiQuota::globalCeilingReached());
        $this->assertFalse((new AiQuota($second))->allows());
    }

    public function test_sync_is_queued_rather_than_run_in_the_request(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $user->forceFill(['hevy_api_key' => 'k'])->save();

        $this->actingAs($user)->post('/sync')->assertRedirect();

        Queue::assertPushed(SyncHevyJob::class, fn ($job) => $job->userId === $user->id);
    }

    public function test_a_second_confirmation_of_the_same_write_does_not_fire_twice(): void
    {
        Http::fake(['*' => Http::response(['id' => 'new-workout'], 200)]);

        $user = User::factory()->create();
        $user->forceFill(['hevy_api_key' => 'k'])->save();

        $op = $user->writeOperations()->create([
            'operation' => 'workout.create',
            'method' => 'POST',
            'endpoint' => '/v1/workouts',
            'payload' => ['title' => 'Test'],
            'status' => 'pending',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $writer = new HevyWriter($user);
        $writer->execute($op);

        // Re-executing the now-succeeded operation must be a no-op: without the
        // status claim, a double-clicked confirm created two real workouts.
        $writer->execute($op->fresh());

        $this->assertSame(1, Http::recorded(
            fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/v1/workouts')
        )->count());
    }

    public function test_workout_creation_sends_an_idempotency_key_and_is_not_auto_retried(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        $user = User::factory()->create();
        $user->forceFill(['hevy_api_key' => 'k'])->save();

        $key = (string) Str::uuid();
        $op = $user->writeOperations()->create([
            'operation' => 'workout.create',
            'method' => 'POST',
            'endpoint' => '/v1/workouts',
            'payload' => ['title' => 'Test'],
            'status' => 'pending',
            'idempotency_key' => $key,
        ]);

        (new HevyWriter($user))->execute($op);

        $posts = Http::recorded(fn ($request) => $request->method() === 'POST');

        // Exactly one attempt: retrying a POST that may already have succeeded
        // would duplicate a workout in the user's real Hevy log.
        $this->assertCount(1, $posts);
        $this->assertSame($key, $posts[0][0]->header('Idempotency-Key')[0] ?? null);

        $this->assertSame('failed', $op->fresh()->status);
    }

    public function test_hevy_sync_command_never_selects_a_user_without_an_api_key(): void
    {
        // AND binds tighter than OR: an ungrouped id/email alternation matched
        // keyless users and handed null to the client.
        $keyless = User::factory()->create();

        $this->artisan('hevy:sync', ['user' => $keyless->id])
            ->expectsOutputToContain('No users with a Hevy API key found.')
            ->assertExitCode(1);
    }
}
