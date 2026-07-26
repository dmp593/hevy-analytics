<?php

namespace App\Services\AI;

/**
 * Decides whether a user-supplied provider base URL is safe to call.
 *
 * Letting an athlete point the app at "any OpenAI-compatible endpoint" is a
 * server-side request forgery hole by construction: the server makes an
 * authenticated outbound request to an address the user chose. Left unguarded
 * that reaches the cloud metadata endpoint (169.254.169.254, which on most
 * providers hands out instance credentials to anyone who asks), anything bound
 * to localhost, and the entire private network the app is deployed into.
 *
 * The rule here is an allow-list of shapes, not a block-list of known-bad
 * strings: https only, a public IP only, and no redirects. A block-list of
 * hostnames is trivially defeated by a DNS record that resolves wherever the
 * attacker likes.
 *
 * Self-hosters running a local model are a real case, so loopback can be
 * permitted — but only when the operator opts in via config, never by default,
 * because the default has to be safe for a hosted deployment.
 */
class UrlGuard
{
    /**
     * Result of a check: either the normalised URL, or the reason it was refused.
     *
     * @return array{ok: bool, url: ?string, reason: ?string}
     */
    public static function check(string $url): array
    {
        $url = trim($url);

        if ($url === '') {
            return self::fail('app.ai.url.empty');
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'], $parts['scheme'])) {
            return self::fail('app.ai.url.malformed');
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        // Credentials in the URL would be logged wherever the URL is, and are
        // never needed: the API key travels in a header.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return self::fail('app.ai.url.credentials');
        }

        $allowLocal = (bool) config('ai.allow_local_providers', false);

        if ($scheme !== 'https' && ! ($allowLocal && $scheme === 'http')) {
            return self::fail('app.ai.url.not_https');
        }

        $ips = self::resolve($host);

        if ($ips === []) {
            return self::fail('app.ai.url.unresolvable');
        }

        // EVERY address the host resolves to must be acceptable. A name with one
        // public and one private A record would otherwise pass the check and be
        // connected to on the private one.
        foreach ($ips as $ip) {
            if (self::isLoopback($ip)) {
                if (! $allowLocal) {
                    return self::fail('app.ai.url.local');
                }

                continue;
            }

            if (! self::isPublic($ip)) {
                return self::fail('app.ai.url.private');
            }
        }

        // Path is kept (providers are often mounted under /v1), query and
        // fragment are not: neither carries meaning for a base URL and both are
        // a way to smuggle something past a reviewer's eye.
        $normalised = $scheme.'://'.$host
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .rtrim($parts['path'] ?? '', '/');

        return ['ok' => true, 'url' => $normalised, 'reason' => null];
    }

    /** Convenience wrapper for callers that only need a yes/no. */
    public static function allows(string $url): bool
    {
        return self::check($url)['ok'];
    }

    /**
     * @return array<int, string>
     */
    private static function resolve(string $host): array
    {
        // A literal address needs no lookup, and passing one to gethostbyname
        // would just echo it back.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        // Strip the brackets from an IPv6 literal host before validating it.
        $unbracketed = trim($host, '[]');
        if ($unbracketed !== $host && filter_var($unbracketed, FILTER_VALIDATE_IP)) {
            return [$unbracketed];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        $ips = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (isset($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    private static function isLoopback(string $ip): bool
    {
        return $ip === '::1' || str_starts_with($ip, '127.');
    }

    /**
     * FILTER_FLAG_NO_PRIV_RANGE and NO_RES_RANGE together reject RFC1918,
     * loopback, link-local (which is where 169.254.169.254 lives), and the
     * reserved blocks — including the IPv6 unique-local and link-local ranges.
     */
    private static function isPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /** @return array{ok: bool, url: ?string, reason: string} */
    private static function fail(string $reason): array
    {
        return ['ok' => false, 'url' => null, 'reason' => $reason];
    }
}
