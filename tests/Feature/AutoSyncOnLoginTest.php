<?php

namespace Tests\Feature;

use App\Jobs\SyncHevyJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Login queues a background sync — but only when it would actually help.
 *
 * The dangerous half of this feature is the quiet one: syncing on EVERY login
 * would multiply Hevy API traffic by however often people open the app. So
 * most of these tests are about NOT syncing.
 */
class AutoSyncOnLoginTest extends TestCase
{
    use RefreshDatabase;

    private function login(User $user): void
    {
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    }

    public function test_a_stale_account_gets_a_sync_queued_on_login(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'hevy_api_key' => '00000000-0000-0000-0000-000000000000',
            'hevy_last_synced_at' => now()->subHours(12),
        ]);

        $this->login($user);

        Queue::assertPushed(SyncHevyJob::class, 1);
        $this->assertSame('queued', $user->syncLogs()->sole()->status);
    }

    public function test_a_never_synced_account_syncs_on_first_login(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'hevy_api_key' => '00000000-0000-0000-0000-000000000000',
            'hevy_last_synced_at' => null,
        ]);

        $this->login($user);

        Queue::assertPushed(SyncHevyJob::class, 1);
    }

    public function test_a_fresh_account_is_left_alone(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'hevy_api_key' => '00000000-0000-0000-0000-000000000000',
            'hevy_last_synced_at' => now()->subHour(),
        ]);

        $this->login($user);

        Queue::assertNothingPushed();
    }

    public function test_no_key_means_no_sync(): void
    {
        Queue::fake();

        $this->login(User::factory()->create(['hevy_api_key' => null]));

        Queue::assertNothingPushed();
    }

    public function test_a_pending_sync_is_not_duplicated(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'hevy_api_key' => '00000000-0000-0000-0000-000000000000',
            'hevy_last_synced_at' => now()->subDay(),
        ]);
        $user->syncLogs()->create(['type' => 'incremental', 'status' => 'running', 'started_at' => now()]);

        $this->login($user);

        Queue::assertNothingPushed();
    }

    public function test_zero_disables_the_feature(): void
    {
        config(['services.hevy.auto_sync_hours' => 0]);
        Queue::fake();

        $user = User::factory()->create([
            'hevy_api_key' => '00000000-0000-0000-0000-000000000000',
            'hevy_last_synced_at' => now()->subYear(),
        ]);

        $this->login($user);

        Queue::assertNothingPushed();
    }

    public function test_the_demo_account_never_syncs(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'is_demo' => true,
            'hevy_api_key' => 'demo-key-not-a-real-credential',
            'hevy_last_synced_at' => now()->subYear(),
        ]);

        $this->login($user);

        Queue::assertNothingPushed();
    }
}
