<?php

namespace App\Services\AI\Drivers;

use App\Services\AI\ChatResult;
use App\Services\AI\Contracts\ChatDriver;
use App\Services\AI\ProviderCredentials;
use App\Services\AI\ProviderException;
use App\Services\AI\ProviderRegistry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic's Messages API, which differs from the OpenAI schema in four ways
 * that all matter:
 *
 *   - the key travels in x-api-key, not as a bearer token
 *   - a version header is mandatory
 *   - the system prompt is a top-level field, not a message with role "system"
 *   - max_tokens is required, and the response is a content block list
 */
class AnthropicDriver implements ChatDriver
{
    /**
     * Pinned deliberately. Anthropic treats this header as the API contract, so
     * leaving it floating would let a server-side change alter the response
     * shape under a deployment that was working.
     */
    private const API_VERSION = '2023-06-01';

    public function provider(): string
    {
        return ProviderRegistry::ANTHROPIC;
    }

    public function chat(ProviderCredentials $credentials, string $systemPrompt, string $userPrompt): ChatResult
    {
        try {
            $response = Http::baseUrl(rtrim($credentials->baseUrl, '/'))
                ->withHeaders([
                    'x-api-key' => $credentials->apiKey,
                    'anthropic-version' => self::API_VERSION,
                ])
                ->timeout((int) config('ai.timeout', 120))
                ->withoutRedirecting()
                ->asJson()
                ->post('/messages', [
                    'model' => $credentials->model,
                    'max_tokens' => (int) config('ai.max_tokens', 2000),
                    'temperature' => 0.4,
                    // Top-level, not a message: Anthropic rejects role "system"
                    // inside the messages array.
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
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

        // content is a list of blocks; only the text ones carry the analysis, and
        // a model that emitted a non-text block first would otherwise yield "".
        $text = collect($response->json('content') ?? [])
            ->filter(fn ($block) => ($block['type'] ?? null) === 'text')
            ->map(fn ($block) => $block['text'] ?? '')
            ->implode('');

        if (trim($text) === '') {
            throw new ProviderException('app.ai.errors.empty', [], 'provider returned no text blocks');
        }

        return new ChatResult(
            content: $text,
            model: $response->json('model') ?? $credentials->model,
            // Named differently from OpenAI's; normalised here so the usage
            // ledger stores one shape whatever the provider.
            promptTokens: $response->json('usage.input_tokens'),
            completionTokens: $response->json('usage.output_tokens'),
        );
    }
}
