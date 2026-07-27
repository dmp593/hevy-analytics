<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\ChatResult;
use App\Services\AI\Contracts\ChatDriver;
use App\Services\AI\PinnedHttp;
use App\Services\AI\ProviderCredentials;
use App\Services\AI\ProviderException;
use App\Services\AI\ProviderRegistry;
use Illuminate\Http\Client\ConnectionException;

/**
 * The OpenAI chat-completions schema, which OpenAI, DeepSeek, Groq, Together,
 * OpenRouter, vLLM, Ollama and LM Studio all speak. One driver covers all of
 * them; only the base URL differs.
 */
class OpenAiDriver implements ChatDriver
{
    public function provider(): string
    {
        return ProviderRegistry::OPENAI;
    }

    public function chat(ProviderCredentials $credentials, string $systemPrompt, string $userPrompt): ChatResult
    {
        try {
            $response = PinnedHttp::to($credentials->baseUrl, $credentials->host, $credentials->ips)
                ->withToken($credentials->apiKey)
                ->timeout((int) config('ai.timeout', 120))
                // Redirects are refused rather than followed. A redirect is a
                // second request to an address UrlGuard never inspected, which
                // would hand the athlete's API key to wherever it points.
                ->withoutRedirecting()
                ->asJson()
                ->post('/chat/completions', [
                    'model' => $credentials->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.4,
                    'max_tokens' => (int) config('ai.max_tokens', 2000),
                    'stream' => false,
                ]);
        } catch (ConnectionException $e) {
            throw new ProviderException('app.ai.errors.unreachable', [], $e->getMessage());
        }

        if ($response->redirect()) {
            throw new ProviderException('app.ai.errors.redirected', [], 'provider responded with a redirect');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new ProviderException('app.ai.errors.rejected_key');
        }

        if ($response->status() === 429) {
            throw new ProviderException('app.ai.errors.rate_limited');
        }

        if (! $response->successful()) {
            throw new ProviderException(
                'app.ai.errors.failed',
                ['status' => $response->status()],
                'provider returned '.$response->status(),
            );
        }

        $content = $response->json('choices.0.message.content');

        // A 200 with no content still cost money and produced nothing, so it is
        // a failure the caller must see rather than an empty analysis to store.
        if (! is_string($content) || trim($content) === '') {
            throw new ProviderException('app.ai.errors.empty', [], 'provider returned no content');
        }

        return new ChatResult(
            content: $content,
            model: $response->json('model') ?? $credentials->model,
            promptTokens: $response->json('usage.prompt_tokens'),
            completionTokens: $response->json('usage.completion_tokens'),
        );
    }
}
