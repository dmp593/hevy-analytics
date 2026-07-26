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
    /** A previously generated analysis for the same metrics, if one exists. */
    public function cached(User $user, string $scope, array $metrics): ?AiAnalysis
    {
        return $user->aiAnalyses()
            ->where('scope', $scope)
            ->where('data_hash', hash('sha256', json_encode($metrics)))
            ->latest('id')
            ->first();
    }

    public function analyze(User $user, string $scope, array $metrics, string $systemPrompt, bool $force = false): ?AiAnalysis
    {
        $hash = hash('sha256', json_encode($metrics));

        if (! $force) {
            $existing = $this->cached($user, $scope, $metrics);
            if ($existing) {
                return $existing;
            }
        }

        if (! $this->configured()) {
            return null;
        }

        $userPrompt = "Here is the athlete's current data as JSON. Analyse it and respond in Markdown.\n\n"
            .json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Recorded BEFORE the call and never rolled back: once the request is on
        // the wire the tokens are spent whatever comes back, so the allowance has
        // to move even when the outcome is useless.
        $usage = $user->aiUsageEvents()->create([
            'scope' => $scope,
            'provider' => 'deepseek',
            'model' => config('services.deepseek.model'),
            'outcome' => 'attempted',
        ]);

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
            $usage->update(['outcome' => 'failed']);

            return null;
        }

        $content = $response->json('choices.0.message.content');
        if (! $content) {
            $usage->update(['outcome' => 'failed']);

            return null;
        }

        $usage->update([
            'outcome' => 'success',
            'prompt_tokens' => $response->json('usage.prompt_tokens'),
            'completion_tokens' => $response->json('usage.completion_tokens'),
        ]);

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
