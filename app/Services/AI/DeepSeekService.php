<?php

namespace App\Services\AI;

use App\Models\AiAnalysis;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * DeepSeek (OpenAI-compatible) client for narrative analysis. Results are
 * cached in ai_analyses keyed by scope + a hash of the metrics payload.
 */
class DeepSeekService
{
    public function configured(): bool
    {
        return filled(config('services.deepseek.key'));
    }

    /**
     * Return a cached analysis if the metrics haven't changed, otherwise
     * generate a fresh one.
     */
    public function analyze(User $user, string $scope, array $metrics, string $systemPrompt, bool $force = false): ?AiAnalysis
    {
        $hash = hash('sha256', json_encode($metrics));

        if (! $force) {
            $existing = $user->aiAnalyses()
                ->where('scope', $scope)
                ->where('data_hash', $hash)
                ->latest('id')
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        if (! $this->configured()) {
            return null;
        }

        $userPrompt = "Here is the athlete's current data as JSON. Analyse it and respond in Markdown.\n\n"
            .json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $response = Http::baseUrl(rtrim(config('services.deepseek.base_url'), '/'))
            ->withToken(config('services.deepseek.key'))
            ->timeout(120)
            ->post('/chat/completions', [
                'model' => config('services.deepseek.model'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.4,
                // Bound the response so a single call cannot run away on cost.
                'max_tokens' => config('services.deepseek.max_tokens', 2000),
                'stream' => false,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $content = $response->json('choices.0.message.content');
        if (! $content) {
            return null;
        }

        return $user->aiAnalyses()->create([
            'scope' => $scope,
            'params' => $metrics,
            'data_hash' => $hash,
            'prompt' => $userPrompt,
            'response' => $content,
            'model' => config('services.deepseek.model'),
        ]);
    }

    public function latest(User $user, string $scope): ?AiAnalysis
    {
        return $user->aiAnalyses()->where('scope', $scope)->latest('id')->first();
    }
}
