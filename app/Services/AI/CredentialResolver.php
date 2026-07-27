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
    /**
     * Resolved once per instance.
     *
     * Not an optimisation — a correctness requirement. Every call to fromUser()
     * re-runs a DNS lookup, so two calls could legitimately disagree. The caller
     * asks "does quota apply?" and then "which credentials?", and with two
     * independent resolutions an athlete serving a zero-TTL record could answer
     * yes to the first (their key, no allowance check) and have the second fall
     * through to the operator's key. The quota decision and the billing identity
     * have to come from the same answer.
     *
     * @var array{0: ?ProviderCredentials}|null
     */
    private ?array $resolved = null;

    public function __construct(private readonly User $user) {}

    public function resolve(): ?ProviderCredentials
    {
        if ($this->resolved === null) {
            $this->resolved = [$this->fromUser() ?? $this->fromApp()];
        }

        return $this->resolved[0];
    }

    /** True when the athlete supplied their own key, so quota does not apply. */
    public function usesOwnKey(): bool
    {
        return $this->resolve()?->ownedByUser === true;
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
        $check = UrlGuard::check($baseUrl);

        if (! $check['ok']) {
            return null;
        }

        return new ProviderCredentials(
            provider: $credential->provider,
            apiKey: $key,
            model: $credential->model,
            // The guard's normalised URL, not the stored string: the stored one
            // may carry a host form that resolves differently at connect time.
            baseUrl: $check['url'],
            ownedByUser: true,
            host: $check['host'],
            ips: $check['ips'],
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

        // The operator's endpoint comes from config, not from a user, so it is
        // not an SSRF surface — but it is still resolved here so the connection
        // is pinned the same way and the code has one path, not two.
        $check = UrlGuard::check($baseUrl);

        return new ProviderCredentials(
            provider: $provider,
            apiKey: $key,
            model: (string) config('services.ai.model', 'deepseek-chat'),
            baseUrl: $check['ok'] ? $check['url'] : $baseUrl,
            ownedByUser: false,
            host: $check['host'] ?? '',
            ips: $check['ips'],
        );
    }
}
