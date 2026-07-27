<?php

namespace App\Services\AI;

/**
 * Everything needed to make one call, resolved from either the athlete's own
 * settings or the operator's app-wide keys.
 *
 * `ownedByUser` decides whether the call counts against the monthly allowance:
 * an athlete spending their own API key is not spending the operator's money,
 * so quota does not apply to them.
 */
class ProviderCredentials
{
    public function __construct(
        public readonly string $provider,
        public readonly string $apiKey,
        public readonly string $model,
        public readonly string $baseUrl,
        public readonly bool $ownedByUser,
        /**
         * The host and addresses UrlGuard approved. Carried so the connection
         * can be pinned to them: re-resolving the name at connect time is
         * exactly the window a DNS-rebinding attack needs.
         *
         * @var array<int, string>
         */
        public readonly string $host = '',
        public readonly array $ips = [],
    ) {}
}
