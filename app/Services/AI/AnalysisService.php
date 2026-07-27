<?php

namespace App\Services\AI;

use App\Models\AiAnalysis;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Generates and caches the written analysis, whichever provider is behind it.
 *
 * Replaces DeepSeekService, which read a single provider straight out of config.
 * The caching, the usage ledger and the content-hash logic are unchanged and
 * were correct; only the part that actually makes the HTTP call moved behind a
 * driver.
 */
class AnalysisService
{
    public function configured(User $user): bool
    {
        return (new CredentialResolver($user))->configured();
    }

    /** A previously generated analysis for exactly these metrics, if one exists. */
    public function cached(User $user, string $scope, array $metrics): ?AiAnalysis
    {
        return $user->aiAnalyses()
            ->where('scope', $scope)
            ->where('data_hash', $this->hash($metrics))
            ->latest('id')
            ->first();
    }

    public function latest(User $user, string $scope): ?AiAnalysis
    {
        return $user->aiAnalyses()->where('scope', $scope)->latest('id')->first();
    }

    /**
     * @throws ProviderException when the provider call fails.
     */
    public function analyze(
        User $user,
        string $scope,
        array $metrics,
        string $systemPrompt,
        bool $force = false,
    ): ?AiAnalysis {
        if (! $force) {
            $existing = $this->cached($user, $scope, $metrics);
            if ($existing) {
                return $existing;
            }
        }

        $credentials = (new CredentialResolver($user))->resolve();

        if (! $credentials) {
            return null;
        }

        $userPrompt = "Here is the athlete's current data as JSON. Analyse it and respond in Markdown.\n\n"
            .json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Recorded BEFORE the call and never rolled back: once the request is on
        // the wire the tokens are spent whatever comes back, so the allowance has
        // to move even when the outcome is useless.
        //
        // A call on the athlete's own key is still recorded — it is their usage
        // history — but AiQuota ignores rows that did not spend the operator's
        // money, so it does not eat an allowance they are not using.
        $usage = $user->aiUsageEvents()->create([
            'scope' => $scope,
            'provider' => $credentials->provider,
            'model' => $credentials->model,
            'outcome' => 'attempted',
            'billed_to_app' => ! $credentials->ownedByUser,
        ]);

        try {
            $result = ProviderRegistry::driver($credentials->provider)
                ->chat($credentials, $systemPrompt, $userPrompt);
        } catch (ProviderException $e) {
            $usage->update(['outcome' => 'failed']);
            $this->recordFailure($user, $credentials, $e);

            throw $e;
        }

        $usage->update([
            'outcome' => 'success',
            'prompt_tokens' => $result->promptTokens,
            'completion_tokens' => $result->completionTokens,
        ]);

        $this->recordSuccess($user, $credentials);

        return $user->aiAnalyses()->create([
            'scope' => $scope,
            'params' => $metrics,
            'data_hash' => $this->hash($metrics),
            // The prompt is stored so an athlete can see exactly what was sent
            // about them. It contains their data, never their key.
            'prompt' => $userPrompt,
            'response' => $result->content,
            'model' => $result->model,
            'provider' => $credentials->provider,
        ]);
    }

    private function hash(array $metrics): string
    {
        return hash('sha256', json_encode($metrics));
    }

    /**
     * Mark the athlete's own key as working, so the settings page can say
     * "verified" rather than leaving them to guess whether it was accepted.
     */
    private function recordSuccess(User $user, ProviderCredentials $credentials): void
    {
        if (! $credentials->ownedByUser) {
            return;
        }

        $user->aiCredentials()
            ->where('provider', $credentials->provider)
            ->update(['last_verified_at' => now(), 'last_error' => null]);
    }

    private function recordFailure(User $user, ProviderCredentials $credentials, ProviderException $e): void
    {
        // Logged without the message, which providers frequently populate by
        // quoting the request back — that would put the athlete's body data in
        // the application log.
        Log::warning('AI provider call failed', [
            'user_id' => $user->id,
            'provider' => $credentials->provider,
            'reason' => $e->translationKey,
        ]);

        if (! $credentials->ownedByUser) {
            return;
        }

        $user->aiCredentials()
            ->where('provider', $credentials->provider)
            ->update(['last_error' => $e->translationKey]);
    }
}
