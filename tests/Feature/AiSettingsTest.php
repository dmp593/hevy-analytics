<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AI\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $overrides = []): array
    {
        return array_merge([
            'provider' => ProviderRegistry::OPENAI,
            'api_key' => 'sk-test-key',
            'model' => 'gpt-4o',
        ], $overrides);
    }

    public function test_an_athlete_can_store_their_own_provider_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings/ai', $this->form())->assertRedirect();

        $credential = $user->aiCredentials()->first();

        $this->assertSame(ProviderRegistry::OPENAI, $credential->provider);
        $this->assertSame('sk-test-key', $credential->api_key);
        // The endpoint comes from the registry for fixed providers, not the form.
        $this->assertSame('https://api.openai.com/v1', $credential->base_url);
        $this->assertTrue($credential->is_active);
    }

    /**
     * The key is write-only in the UI, so an athlete changing their model has no
     * way to retype a secret they cannot read. A blank field must mean "keep it",
     * not "erase it".
     */
    public function test_saving_with_a_blank_key_keeps_the_existing_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/settings/ai', $this->form())->assertRedirect();

        $this->actingAs($user)
            ->put('/settings/ai', $this->form(['api_key' => '', 'model' => 'gpt-4o-mini']))
            ->assertRedirect();

        $credential = $user->aiCredentials()->first();

        $this->assertSame('sk-test-key', $credential->api_key);
        $this->assertSame('gpt-4o-mini', $credential->model);
    }

    public function test_a_first_save_with_no_key_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/settings/ai', $this->form(['api_key' => '']))
            ->assertSessionHasErrors('api_key');
    }

    public function test_only_one_provider_is_active_at_a_time_and_the_others_are_kept(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings/ai', $this->form())->assertRedirect();
        $this->actingAs($user)->put('/settings/ai', $this->form([
            'provider' => ProviderRegistry::ANTHROPIC,
            'api_key' => 'sk-ant-test',
            'model' => 'claude-sonnet-4-5',
        ]))->assertRedirect();

        $this->assertSame(2, $user->aiCredentials()->count(), 'the old key is kept, not deleted');
        $this->assertSame(1, $user->aiCredentials()->where('is_active', true)->count());
        $this->assertSame(
            ProviderRegistry::ANTHROPIC,
            $user->aiCredentials()->where('is_active', true)->first()->provider,
        );
    }

    /**
     * A custom endpoint is the SSRF surface. It must be refused at save time with
     * a message the athlete can act on, not silently accepted and then blocked.
     */
    public function test_a_private_endpoint_is_refused_with_a_field_error(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/settings/ai', $this->form([
                'provider' => ProviderRegistry::COMPATIBLE,
                'base_url' => 'https://169.254.169.254/v1',
            ]))
            ->assertSessionHasErrors('base_url');
    }

    public function test_a_non_https_endpoint_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/settings/ai', $this->form([
                'provider' => ProviderRegistry::COMPATIBLE,
                'base_url' => 'http://1.1.1.1/v1',
            ]))
            ->assertSessionHasErrors('base_url');
    }

    public function test_a_public_custom_endpoint_is_accepted_and_normalised(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings/ai', $this->form([
            'provider' => ProviderRegistry::COMPATIBLE,
            'base_url' => 'https://1.1.1.1/v1/?token=x',
        ]))->assertRedirect();

        $this->assertSame('https://1.1.1.1/v1', $user->aiCredentials()->first()->base_url);
    }

    public function test_an_unknown_provider_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/settings/ai', $this->form(['provider' => 'not-a-provider']))
            ->assertSessionHasErrors('provider');
    }

    public function test_an_athlete_can_remove_their_key(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/settings/ai', $this->form())->assertRedirect();

        $this->actingAs($user)->delete('/settings/ai/'.ProviderRegistry::OPENAI)->assertRedirect();

        $this->assertSame(0, $user->aiCredentials()->count());
    }

    public function test_one_athlete_cannot_remove_another_athletes_key(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->put('/settings/ai', $this->form())->assertRedirect();

        $other = User::factory()->create();
        $this->actingAs($other)->delete('/settings/ai/'.ProviderRegistry::OPENAI)->assertRedirect();

        $this->assertSame(1, $owner->aiCredentials()->count());
    }

    public function test_saving_a_new_key_clears_the_previous_verification_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/settings/ai', $this->form())->assertRedirect();

        $user->aiCredentials()->update(['last_verified_at' => now(), 'last_error' => 'app.ai.errors.rejected_key']);

        $this->actingAs($user)->put('/settings/ai', $this->form(['api_key' => 'sk-a-different-key']))->assertRedirect();

        $credential = $user->aiCredentials()->first();

        $this->assertNull($credential->last_verified_at);
        $this->assertNull($credential->last_error);
    }

    public function test_the_settings_page_never_renders_the_stored_key(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/settings/ai', $this->form())->assertRedirect();

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertDontSee('sk-test-key');
    }

    public function test_settings_routes_require_authentication(): void
    {
        $this->put('/settings/ai', $this->form())->assertRedirect('/login');
        $this->delete('/settings/ai/'.ProviderRegistry::OPENAI)->assertRedirect('/login');
    }

    /**
     * Guzzle rejects a header value containing CR or LF by throwing an
     * InvalidArgumentException whose message quotes the whole value — so a key
     * with an embedded newline ends up in the application log verbatim, and the
     * AI page 500s instead of failing cleanly.
     */
    public function test_a_key_containing_control_characters_is_rejected_at_the_form(): void
    {
        foreach (["sk-real\r\nX-Injected: 1", "sk-real\nfoo", 'sk with space', "sk-real\ttab"] as $bad) {
            $this->actingAs(User::factory()->create())
                ->put('/settings/ai', $this->form(['api_key' => $bad]))
                ->assertSessionHasErrors('api_key');
        }
    }

    public function test_a_normal_provider_key_shape_is_still_accepted(): void
    {
        foreach (['sk-proj-AbC123_-xyz', 'sk-ant-api03-AAAA', 'gsk_abc123'] as $good) {
            $user = User::factory()->create();

            $this->actingAs($user)
                ->put('/settings/ai', $this->form(['api_key' => $good]))
                ->assertSessionHasNoErrors();

            $this->assertSame($good, $user->aiCredentials()->first()->api_key);
        }
    }

    /**
     * The failure the guard exists to produce arrives with a real key sitting in
     * the same POST body. Laravel would flash it into the session in plaintext.
     */
    public function test_a_rejected_submission_does_not_flash_the_key_into_the_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/settings/ai', $this->form([
                'provider' => ProviderRegistry::COMPATIBLE,
                'api_key' => 'sk-a-real-secret-key',
                'base_url' => 'https://10.0.0.5/v1',
            ]))
            ->assertSessionHasErrors('base_url');

        $flashed = session()->getOldInput();

        $this->assertArrayNotHasKey('api_key', $flashed);
        $this->assertStringNotContainsString('sk-a-real-secret-key', json_encode($flashed));
    }

    public function test_a_rejected_profile_submission_does_not_flash_the_hevy_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => '',                       // forces a validation failure
            'email' => $user->email,
            'hevy_api_key' => 'hevy-real-secret',
        ])->assertSessionHasErrors('name');

        $this->assertStringNotContainsString('hevy-real-secret', json_encode(session()->getOldInput()));
    }
}
