<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An APP_KEY rotation must degrade to "secret no longer on file", never to an
 * exception on every page that touches the attribute.
 */
class KeyRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_key_encrypted_under_an_old_app_key_reads_as_absent(): void
    {
        $user = User::factory()->create();

        // Simulate a value written under a different APP_KEY: raw ciphertext
        // the current key cannot open.
        $user->forceFill([])->save();
        \DB::table('users')->where('id', $user->id)
            ->update(['hevy_api_key' => 'eyJpdiI6bm90LXJlYWwtY2lwaGVydGV4dCJ9']);

        $fresh = $user->fresh();

        $this->assertNull($fresh->hevy_api_key);
        $this->assertFalse($fresh->hasHevyKey());

        // And the profile page renders rather than 500ing.
        $this->actingAs($fresh)->get('/profile')->assertOk();
    }

    public function test_a_key_under_the_current_app_key_still_round_trips(): void
    {
        $user = User::factory()->create(['hevy_api_key' => 'real-key-123']);

        $this->assertSame('real-key-123', $user->fresh()->hevy_api_key);
        $this->assertNotSame('real-key-123', \DB::table('users')->where('id', $user->id)->value('hevy_api_key'));

        $ai = $user->aiCredentials()->create([
            'provider' => 'deepseek',
            'model' => 'deepseek-chat',
            'base_url' => 'https://api.deepseek.com',
            'api_key' => 'sk-abc',
        ]);
        $this->assertSame('sk-abc', $ai->fresh()->api_key);
    }
}
