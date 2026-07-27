<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AI\AiQuota;
use App\Services\AI\AnalysisService;
use App\Services\AI\CredentialResolver;
use App\Services\AI\ProviderException;
use App\Services\AI\ProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    private function openAiResponse(string $content = 'Analysis text'): array
    {
        return [
            'model' => 'gpt-4o',
            'choices' => [['message' => ['content' => $content]]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
        ];
    }

    private function anthropicResponse(string $content = 'Analysis text'): array
    {
        return [
            'model' => 'claude-sonnet-4-5',
            'content' => [['type' => 'text', 'text' => $content]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ];
    }

    private function withKey(User $user, string $provider, string $model, ?string $baseUrl = null): void
    {
        $user->aiCredentials()->create([
            'provider' => $provider,
            'api_key' => 'sk-user-secret',
            'model' => $model,
            'base_url' => $baseUrl ?? ProviderRegistry::defaultBaseUrl($provider),
            'is_active' => true,
        ]);
    }

    public function test_an_openai_key_is_sent_as_a_bearer_token(): void
    {
        $user = User::factory()->create();
        $this->withKey($user, ProviderRegistry::OPENAI, 'gpt-4o');

        Http::fake(['api.openai.com/*' => Http::response($this->openAiResponse())]);

        $analysis = app(AnalysisService::class)->analyze($user, 'deep_analysis', ['a' => 1], 'system');

        $this->assertSame('Analysis text', $analysis->response);

        Http::assertSent(function ($request) {
            $this->assertSame('Bearer sk-user-secret', $request->header('Authorization')[0]);
            $this->assertStringEndsWith('/chat/completions', $request->url());
            $this->assertSame('system', $request['messages'][0]['content']);

            return true;
        });
    }

    /**
     * Anthropic differs in every part of the envelope, which is the whole reason
     * a driver layer exists.
     */
    public function test_an_anthropic_key_uses_the_messages_api_shape(): void
    {
        $user = User::factory()->create();
        $this->withKey($user, ProviderRegistry::ANTHROPIC, 'claude-sonnet-4-5');

        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicResponse())]);

        $analysis = app(AnalysisService::class)->analyze($user, 'deep_analysis', ['a' => 1], 'system prompt');

        $this->assertSame('Analysis text', $analysis->response);

        Http::assertSent(function ($request) {
            $this->assertSame('sk-user-secret', $request->header('x-api-key')[0]);
            $this->assertSame('2023-06-01', $request->header('anthropic-version')[0]);
            $this->assertStringEndsWith('/messages', $request->url());
            // System prompt is top level, not a message with role "system".
            $this->assertSame('system prompt', $request['system']);
            $this->assertSame('user', $request['messages'][0]['role']);

            return true;
        });
    }

    public function test_anthropic_token_counts_are_normalised_into_the_usage_ledger(): void
    {
        $user = User::factory()->create();
        $this->withKey($user, ProviderRegistry::ANTHROPIC, 'claude-sonnet-4-5');

        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicResponse())]);

        app(AnalysisService::class)->analyze($user, 'deep_analysis', ['a' => 1], 'system');

        $event = $user->aiUsageEvents()->latest('id')->first();

        $this->assertSame(100, $event->prompt_tokens);
        $this->assertSame(50, $event->completion_tokens);
        $this->assertSame('success', $event->outcome);
    }

    /**
     * The monthly allowance exists to bound the operator's API bill. Rationing
     * someone spending their own key charges them twice for the same request.
     */
    public function test_calls_on_the_athletes_own_key_do_not_consume_the_operator_allowance(): void
    {
        config(['services.ai.monthly_limit_per_user' => 2]);

        $user = User::factory()->create();
        $this->withKey($user, ProviderRegistry::OPENAI, 'gpt-4o');

        Http::fake(['api.openai.com/*' => Http::response($this->openAiResponse())]);

        foreach (range(1, 5) as $i) {
            app(AnalysisService::class)->analyze($user, 'deep_analysis', ['n' => $i], 'system');
        }

        $this->assertSame(5, $user->aiUsageEvents()->count(), 'usage is still recorded as their history');
        $this->assertSame(0, (new AiQuota($user))->usedThisMonth());
        $this->assertTrue((new AiQuota($user))->allows());
    }

    public function test_calls_on_the_operator_key_do_consume_the_allowance(): void
    {
        config([
            'services.ai.key' => 'app-key',
            'services.ai.provider' => ProviderRegistry::DEEPSEEK,
            'services.ai.monthly_limit_per_user' => 5,
        ]);

        $user = User::factory()->create();

        Http::fake(['api.deepseek.com/*' => Http::response($this->openAiResponse())]);

        app(AnalysisService::class)->analyze($user, 'deep_analysis', ['n' => 1], 'system');

        $this->assertSame(1, (new AiQuota($user))->usedThisMonth());
    }

    public function test_the_athletes_own_key_takes_priority_over_the_operator_key(): void
    {
        config(['services.ai.key' => 'app-key', 'services.ai.provider' => ProviderRegistry::DEEPSEEK]);

        $user = User::factory()->create();
        $this->withKey($user, ProviderRegistry::OPENAI, 'gpt-4o');

        $resolver = new CredentialResolver($user);

        $this->assertTrue($resolver->usesOwnKey());
        $this->assertSame('sk-user-secret', $resolver->resolve()->apiKey);
        $this->assertSame(ProviderRegistry::OPENAI, $resolver->resolve()->provider);
    }

    /**
     * A hostname that was public when it was saved can be repointed at a private
     * address afterwards, so the stored URL is re-checked on every use rather
     * than trusted because it passed validation once.
     */
    public function test_a_stored_endpoint_that_became_private_is_refused_at_use_time(): void
    {
        $user = User::factory()->create();

        // Written straight to the table, bypassing the form's validation, which
        // is exactly what a later DNS change amounts to.
        $user->aiCredentials()->create([
            'provider' => ProviderRegistry::COMPATIBLE,
            'api_key' => 'sk-user-secret',
            'model' => 'local-model',
            'base_url' => 'https://169.254.169.254/v1',
            'is_active' => true,
        ]);

        $resolver = new CredentialResolver($user);

        $this->assertFalse($resolver->usesOwnKey());
        $this->assertNull($resolver->resolve());
    }

    public function test_a_rejected_key_is_reported_as_such_and_recorded_against_the_credential(): void
    {
        $user = User::factory()->create();
        $this->withKey($user, ProviderRegistry::OPENAI, 'gpt-4o');

        Http::fake(['api.openai.com/*' => Http::response(['error' => 'nope'], 401)]);

        try {
            app(AnalysisService::class)->analyze($user, 'deep_analysis', ['a' => 1], 'system');
            $this->fail('expected a ProviderException');
        } catch (ProviderException $e) {
            $this->assertSame('app.ai.errors.rejected_key', $e->translationKey);
        }

        $this->assertSame('app.ai.errors.rejected_key', $user->aiCredentials()->first()->last_error);
        $this->assertSame('failed', $user->aiUsageEvents()->latest('id')->first()->outcome);
    }

    /**
     * A 200 with no content still spent tokens. Treating it as success would
     * store an empty analysis; treating it as nothing would let it be retried
     * for free forever.
     */
    public function test_an_empty_response_is_an_error_and_still_costs_an_attempt(): void
    {
        config(['services.ai.key' => 'app-key', 'services.ai.provider' => ProviderRegistry::DEEPSEEK]);

        $user = User::factory()->create();

        Http::fake(['api.deepseek.com/*' => Http::response($this->openAiResponse(''))]);

        $this->expectException(ProviderException::class);

        try {
            app(AnalysisService::class)->analyze($user, 'deep_analysis', ['a' => 1], 'system');
        } finally {
            $this->assertSame(1, (new AiQuota($user))->usedThisMonth());
            $this->assertSame(0, $user->aiAnalyses()->count());
        }
    }

    /** A successful call marks the key verified, so settings can say so. */
    public function test_a_successful_call_marks_the_key_verified(): void
    {
        $user = User::factory()->create();
        $this->withKey($user, ProviderRegistry::OPENAI, 'gpt-4o');

        Http::fake(['api.openai.com/*' => Http::response($this->openAiResponse())]);

        app(AnalysisService::class)->analyze($user, 'deep_analysis', ['a' => 1], 'system');

        $this->assertNotNull($user->aiCredentials()->first()->last_verified_at);
        $this->assertNull($user->aiCredentials()->first()->last_error);
    }

    public function test_the_api_key_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $this->withKey($user, ProviderRegistry::OPENAI, 'gpt-4o');

        $raw = \DB::table('ai_credentials')->where('user_id', $user->id)->value('api_key');

        $this->assertNotSame('sk-user-secret', $raw);
        $this->assertStringNotContainsString('sk-user-secret', $raw);
        // …and still round-trips through the model.
        $this->assertSame('sk-user-secret', $user->aiCredentials()->first()->api_key);
    }

    public function test_the_masked_key_never_exposes_the_middle(): void
    {
        $user = User::factory()->create();
        $this->withKey($user, ProviderRegistry::OPENAI, 'gpt-4o');

        $masked = $user->aiCredentials()->first()->maskedKey();

        $this->assertStringNotContainsString('user-sec', $masked);
        $this->assertStringStartsWith('sk-u', $masked);
    }

    public function test_with_no_key_anywhere_nothing_is_configured(): void
    {
        config(['services.ai.key' => '']);

        $this->assertFalse((new CredentialResolver(User::factory()->create()))->configured());
    }
}
