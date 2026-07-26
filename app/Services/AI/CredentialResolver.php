<?php

namespace App\Services\AI;

use App\Models\User;

/**
 * Decides which credentials a call should use, and therefore who pays for it.
 *
 * Two modes, in priority order:
 *
 *   1. The athlete's own key. They are spending their own account, so the
 *      operator's monthly allowance does not apply and there is nothing to ration.
 *   2. The operator's app-wide key, if configured. This is the included-with-
 *      subscription path and it is quota-limited, because it is the operator's
 *      money and an unbounded free tier is how an API bill becomes a surprise.
 *
 * Keeping this in one place matters: "does this call count against quota" is
 * exactly the kind of question that gets answered differently in three files and
 * then gets one of them wrong.
 */
class CredentialResolver
{
    public function __construct(private readonly User $user) {}

    public function resolve(): ?ProviderCredentials
    {
        return $this->fromUser() ?? $this->fromApp();
    }

    /** True when the athlete supplied their own key, so quota does not apply. */
    public function usesOwnKey(): bool
    {
        return $this->fromUser() !== null;
    }

    public function configured(): bool
    {
        return $this->resolve() !== null;
    }

    private function fromUser(): ?ProviderCredentials
    {
        $credential = $this->user->aiCredentials()
            ->where('is_active', true)
            ->first();

        if (! $credential || ! ProviderRegistry::has($credential->provider)) {
            return null;
        }

        $key = (string) $credential->api_key;
        $baseUrl = (string) $credential->base_url;

        if ($key === '' || $baseUrl === '') {
            return null;
        }

        // Re-checked on every use, not only when it was saved. A hostname that
        // was public when the athlete entered it can be repointed at a private
        // address afterwards, and the stored row would still look fine.
        if (! UrlGuard::allows($baseUrl)) {
            return null;
        }

        return new ProviderCredentials(
            provider: $credential->provider,
            apiKey: $key,
            model: $credential->model,
            baseUrl: $baseUrl,
            ownedByUser: true,
        );
    }

    private function fromApp(): ?ProviderCredentials
    {
        $provider = (string) config('services.ai.provider', ProviderRegistry::DEEPSEEK);
        $key = (string) config('services.ai.key', '');

        if ($key === '' || ! ProviderRegistry::has($provider)) {
            return null;
        }

        $baseUrl = (string) (config('services.ai.base_url') ?: ProviderRegistry::defaultBaseUrl($provider));

        if ($baseUrl === '') {
            return null;
        }

        return new ProviderCredentials(
            provider: $provider,
            apiKey: $key,
            model: (string) config('services.ai.model', 'deepseek-chat'),
            baseUrl: $baseUrl,
            ownedByUser: false,
        );
    }
}
