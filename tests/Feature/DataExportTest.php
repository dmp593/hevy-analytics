<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AI\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    private function exportOf(User $user): array
    {
        $response = $this->actingAs($user)->get('/settings/export')->assertOk();

        return json_decode($this->streamed($response), true, flags: JSON_THROW_ON_ERROR);
    }

    private function streamed($response): string
    {
        ob_start();
        $response->baseResponse->sendContent();

        return ob_get_clean();
    }

    public function test_the_export_contains_the_athletes_own_records(): void
    {
        $user = User::factory()->create(['sex' => 'female', 'height_cm' => 170]);
        $user->goals()->create(['type' => 'cut', 'is_active' => true, 'started_at' => now()]);
        $user->bodyMeasurements()->create(['date' => now(), 'weight_kg' => 68.4]);
        $user->intakeLogs()->create(['date' => now(), 'calories' => 2100]);

        $data = $this->exportOf($user);

        $this->assertSame($user->email, $data['account']['email']);
        $this->assertSame('female', $data['account']['sex']);
        $this->assertCount(1, $data['goals']);
        $this->assertCount(1, $data['body_measurements']);
        $this->assertSame(2100, (int) $data['intake_logs'][0]['calories']);
    }

    /**
     * An export is a file people email to themselves and leave in a downloads
     * folder. A credential in it is a leak with a helpful filename.
     */
    public function test_no_api_key_appears_anywhere_in_the_export(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['hevy_api_key' => 'hevy-secret-value'])->save();
        $user->aiCredentials()->create([
            'provider' => ProviderRegistry::OPENAI,
            'api_key' => 'sk-provider-secret',
            'model' => 'gpt-4o',
            'base_url' => 'https://api.openai.com/v1',
            'is_active' => true,
        ]);

        $raw = $this->streamed($this->actingAs($user)->get('/settings/export')->assertOk());

        $this->assertStringNotContainsString('hevy-secret-value', $raw);
        $this->assertStringNotContainsString('sk-provider-secret', $raw);
        $this->assertStringNotContainsString('hevy_api_key', $raw);

        // The provider is still listed, so the athlete knows what was configured.
        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(ProviderRegistry::OPENAI, $data['ai_providers'][0]['provider']);
        $this->assertArrayNotHasKey('api_key', $data['ai_providers'][0]);
    }

    public function test_the_export_never_includes_another_athletes_data(): void
    {
        $mine = User::factory()->create();
        $mine->bodyMeasurements()->create(['date' => now(), 'weight_kg' => 70]);

        $theirs = User::factory()->create();
        $theirs->bodyMeasurements()->create(['date' => now(), 'weight_kg' => 999]);

        $raw = $this->streamed($this->actingAs($mine)->get('/settings/export')->assertOk());

        $this->assertStringNotContainsString('999', $raw);
        $this->assertStringNotContainsString($theirs->email, $raw);
    }

    public function test_it_is_sent_as_a_download(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/settings/export')->assertOk();

        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.json', $response->headers->get('Content-Disposition'));
    }

    public function test_it_requires_authentication(): void
    {
        $this->get('/settings/export')->assertRedirect('/login');
    }

    public function test_an_account_with_no_data_still_exports_cleanly(): void
    {
        $data = $this->exportOf(User::factory()->create());

        $this->assertSame([], $data['workouts']);
        $this->assertSame([], $data['body_measurements']);
        $this->assertArrayHasKey('exported_at', $data);
    }
}
