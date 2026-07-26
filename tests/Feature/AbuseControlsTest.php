<?php

namespace Tests\Feature;

use App\Jobs\SyncHevyJob;
use App\Models\User;
use App\Services\AI\AiQuota;
use App\Services\Hevy\HevyWriter;
use App\Services\Hevy\SyncStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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
        config(['services.ai.key' => 'test-key', 'services.ai.monthly_limit_per_user' => 2]);

        $user = User::factory()->create();
        $user->forceFill(['hevy_api_key' => 'k'])->save();

        foreach (range(1, 2) as $ignored) {
            $user->aiUsageEvents()->create(['scope' => 'deep_analysis', 'outcome' => 'success']);
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
        $first->aiUsageEvents()->create(['scope' => 'deep_analysis', 'outcome' => 'success']);

        $second = User::factory()->create();

        $this->assertTrue(AiQuota::globalCeilingReached());
        $this->assertFalse((new AiQuota($second))->allows());
    }

    public function test_a_billed_call_that_returns_nothing_still_consumes_quota(): void
    {
        config(['services.ai.key' => 'test-key', 'services.ai.monthly_limit_per_user' => 3]);

        $user = User::factory()->create();

        // 200 OK with empty content: the provider billed the tokens and no
        // analysis is stored. Counting stored analyses let this run forever.
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '']]]], 200)]);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post('/ai/generate', ['force' => 1]);
        }

        $calls = Http::recorded(fn ($r) => str_contains($r->url(), 'chat/completions'))->count();

        $this->assertSame(3, $calls, 'the quota must stop billed calls even when none of them produce an analysis');
        $this->assertSame(3, (new AiQuota($user))->usedThisMonth());
        $this->assertSame(0, $user->aiAnalyses()->count());
    }

    public function test_rapid_sync_clicks_queue_only_one_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $user->forceFill(['hevy_api_key' => 'k'])->save();

        foreach (range(1, 5) as $ignored) {
            $this->actingAs($user)->post('/sync');
        }

        Queue::assertPushed(SyncHevyJob::class, 1);
        $this->assertSame(1, $user->syncLogs()->count());
    }

    public function test_a_queue_nobody_is_consuming_is_visible_to_the_user(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $user->forceFill(['hevy_api_key' => 'k'])->save();

        $this->actingAs($user)->post('/sync');

        // Straight away it just reads as queued.
        $this->assertSame('queued', (new SyncStatus($user))->current()['state']);

        // Still unclaimed several minutes later means there is no worker, and
        // saying "queued" forever would be the silent failure that moving sync
        // onto the queue introduced.
        $user->syncLogs()->update(['created_at' => now()->subMinutes(10)]);

        $status = (new SyncStatus($user->fresh()))->current();
        $this->assertSame('stalled', $status['state']);
        $this->assertStringContainsString('queue:work', $status['message']);

        $this->actingAs($user)->get('/dashboard')->assertSee('queue:work');
    }

    public function test_a_sync_job_that_dies_does_not_leave_the_ui_saying_queued(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['hevy_api_key' => 'k'])->save();

        $log = $user->syncLogs()->create(['type' => 'incremental', 'status' => 'queued']);

        (new SyncHevyJob($user->id, false, $log->id))->failed(new \RuntimeException('worker died'));

        $this->assertSame('failed', $log->fresh()->status);
        $this->assertSame('failed', (new SyncStatus($user))->current()['state']);
    }

    public function test_a_stalled_write_offers_a_retry_button(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['hevy_api_key' => 'k'])->save();

        $op = $user->writeOperations()->create([
            'operation' => 'workout.create',
            'method' => 'POST',
            'endpoint' => '/v1/workouts',
            'payload' => ['title' => 'Test'],
            'status' => 'running',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        // Freshly claimed: nothing to offer, the write may still be in flight.
        $this->actingAs($user)->get('/write-operations')->assertDontSee('Retry');

        // Abandoned: the service would reclaim this, so the page has to say so —
        // the reclaim path previously had no UI entry point at all.
        $op->forceFill(['updated_at' => now()->subMinutes(30)])->saveQuietly();

        $this->actingAs($user)->get('/write-operations')
            ->assertSee('Retry')
            ->assertSee('stalled');
    }

    public function test_a_write_stuck_mid_call_is_recoverable(): void
    {
        $timeout = true;
        Http::fake(function () use (&$timeout) {
            if ($timeout) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            return Http::response(['id' => 'x'], 200);
        });

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

        (new HevyWriter($user))->execute($op);

        // A transport failure previously left the row claimed as 'running'
        // forever, and 'running' is not an executable status.
        $this->assertSame('failed', $op->fresh()->status);

        $timeout = false;
        $retried = (new HevyWriter($user))->execute($op->fresh());
        $this->assertSame('success', $retried->status);
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
