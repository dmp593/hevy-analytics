<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;

/**
 * Decides whether a user-supplied provider base URL is safe to call.
 *
 * Letting an athlete point the app at "any OpenAI-compatible endpoint" is a
 * server-side request forgery hole by construction: the server makes an
 * authenticated outbound request to an address the user chose. Left unguarded
 * that reaches cloud metadata endpoints (which hand out instance credentials to
 * anything that asks), anything bound to localhost, and the entire private
 * network the app is deployed into.
 *
 * Three rules, each learned from a way the naive version was defeated:
 *
 *   1. NORMALISE THE HOST FIRST. libcurl applies UTS-46 before its own lookup,
 *      so a host containing U+24DB ("ⓛocalhost.attacker.com") is resolved by
 *      this guard as the raw bytes and by curl as "localhost.attacker.com" — two
 *      different names, both under the attacker's control via a wildcard record.
 *      The guard approved one and curl connected to the other.
 *
 *   2. DECIDE ON ADDRESSES, NOT ON NAMES, AND HAND THEM BACK. Validating a
 *      hostname and then giving the hostname to the HTTP client leaves the
 *      client free to resolve it again and get a different answer. check()
 *      returns the addresses it approved so the caller can pin the connection to
 *      them; see PinnedHttp.
 *
 *   3. KNOW WHICH RANGES ARE ACTUALLY UNSAFE. PHP's NO_PRIV_RANGE and
 *      NO_RES_RANGE flags predate RFC 6598 and know nothing about
 *      provider-specific metadata addresses, so they call Alibaba's
 *      100.100.100.200, Oracle's 192.0.0.192, all of CGNAT and the NAT64
 *      translation prefix "public". The blocks below are checked explicitly.
 *
 * Self-hosters running a local model are a real case, so loopback can be
 * permitted — but only when the operator opts in, never by default, because the
 * default has to be safe for a hosted deployment.
 */
class UrlGuard
{
    /**
     * Ranges PHP's filter flags treat as public but which must never be
     * reachable. Everything here is either a documented metadata endpoint, a
     * space used for internal networking, or a translation prefix that maps
     * straight back onto one of the above.
     *
     * @var array<int, array{0: string, 1: int}> [network, prefix length]
     */
    private const EXTRA_DENIED_V4 = [
        ['100.64.0.0', 10],    // RFC 6598 CGNAT — node/pod space on several managed Kubernetes offerings
        ['100.100.0.0', 16],   // Alibaba Cloud instance metadata (100.100.100.200)
        ['192.0.0.0', 24],     // IETF protocol assignments, incl. Oracle Cloud Classic metadata (192.0.0.192)
        ['192.0.2.0', 24],     // TEST-NET-1
        ['198.18.0.0', 15],    // RFC 2544 benchmarking
        ['198.51.100.0', 24],  // TEST-NET-2
        ['203.0.113.0', 24],   // TEST-NET-3
        ['224.0.0.0', 4],      // multicast
    ];

    /** @var array<int, array{0: string, 1: int}> */
    private const EXTRA_DENIED_V6 = [
        ['64:ff9b::', 96],     // RFC 6052 NAT64 — 64:ff9b::7f00:1 is 127.0.0.1 on a NAT64 network
        ['64:ff9b:1::', 48],   // RFC 8215 local-use NAT64
        ['100::', 64],         // discard-only
        ['2001:db8::', 32],    // documentation
        ['ff00::', 8],         // multicast
    ];

    /**
     * @return array{ok: bool, url: ?string, host: ?string, ips: array<int, string>, reason: ?string}
     *                                    `host` and `ips` are what the caller must connect to; using
     *                                    anything else reopens the rebinding window.
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

        // Credentials in the URL would be logged wherever the URL is, and are
        // never needed: the API key travels in a header.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return self::fail('app.ai.url.credentials');
        }

        $scheme = strtolower($parts['scheme']);
        $allowLocal = (bool) config('ai.allow_local_providers', false);

        if ($scheme !== 'https' && ! ($allowLocal && $scheme === 'http')) {
            return self::fail('app.ai.url.not_https');
        }

        $host = self::normaliseHost($parts['host']);

        if ($host === null) {
            return self::fail('app.ai.url.malformed');
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

        return ['ok' => true, 'url' => $normalised, 'host' => $host, 'ips' => $ips, 'reason' => null];
    }

    /** Convenience wrapper for callers that only need a yes/no. */
    public static function allows(string $url): bool
    {
        return self::check($url)['ok'];
    }

    /**
     * Convert the host to the exact ASCII form libcurl will use, so the name
     * this guard resolves and the name the socket resolves cannot differ.
     *
     * Public because it is part of this class's contract, not an implementation
     * detail: "which name will actually be looked up" is the question the whole
     * guard depends on, and it must be testable without staging attacker DNS.
     *
     * Returns null when the host cannot be reduced to ASCII. That is deliberately
     * fail-closed: if we cannot predict what the client will look up, we must not
     * approve it. An IP literal is returned unchanged — idn_to_ascii would mangle
     * a bracketed IPv6 address.
     */
    public static function normaliseHost(string $host): ?string
    {
        $host = trim($host);

        if ($host === '') {
            return null;
        }

        // IP literals, bracketed or bare, bypass name processing entirely.
        $unbracketed = trim($host, '[]');
        if (filter_var($unbracketed, FILTER_VALIDATE_IP)) {
            return $unbracketed;
        }

        // A bracket that did not wrap a valid IP is malformed, not a hostname.
        if ($unbracketed !== $host) {
            return null;
        }

        if (self::isAscii($host)) {
            return strtolower($host);
        }

        // Non-ASCII from here on. Without intl we cannot reproduce curl's
        // mapping, so the only safe answer is no.
        if (! function_exists('idn_to_ascii')) {
            return null;
        }

        $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        // Must succeed AND must actually be ASCII afterwards; a mapping that
        // left non-ASCII behind means we still cannot predict the lookup.
        if ($ascii === false || $ascii === '' || ! self::isAscii($ascii)) {
            return null;
        }

        return strtolower($ascii);
    }

    private static function isAscii(string $value): bool
    {
        return preg_match('/[^\x20-\x7e]/', $value) !== 1;
    }

    /**
     * How long a resolution is reused.
     *
     * Short on purpose. The point of re-checking at call time is that a record
     * can change, so caching for long would recreate the hole this guard exists
     * to close. Sixty seconds is enough to stop a page load costing a DNS
     * round-trip per render — the AI page resolves on every GET, and an athlete
     * pointing at a deliberately slow nameserver could otherwise hold a
     * PHP worker open for the resolver timeout just by refreshing.
     */
    private const RESOLVE_CACHE_SECONDS = 60;

    /**
     * @return array<int, string>
     */
    private static function resolve(string $host): array
    {
        // A literal address needs no lookup, and passing one to a resolver would
        // just echo it back.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        return Cache::remember(
            'ai:dns:'.sha1($host),
            self::RESOLVE_CACHE_SECONDS,
            fn () => self::lookup($host),
        );
    }

    /** @return array<int, string> */
    private static function lookup(string $host): array
    {
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
     * FILTER_FLAG_NO_PRIV_RANGE and NO_RES_RANGE reject RFC1918, loopback,
     * link-local (where 169.254.169.254 lives), 0/8, 240/4 and the IPv6 reserved
     * blocks. They do not reject everything that must be unreachable, so the
     * explicit lists above are checked as well.
     */
    private static function isPublic(string $ip): bool
    {
        $passesPhpFlags = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;

        if (! $passesPhpFlags) {
            return false;
        }

        $isV6 = str_contains($ip, ':');

        foreach ($isV6 ? self::EXTRA_DENIED_V6 : self::EXTRA_DENIED_V4 as [$network, $bits]) {
            if (self::inRange($ip, $network, $bits)) {
                return false;
            }
        }

        return true;
    }

    /** Prefix comparison done on packed bytes, so it works for v4 and v6 alike. */
    private static function inRange(string $ip, string $network, int $bits): bool
    {
        $ipBin = @inet_pton($ip);
        $netBin = @inet_pton($network);

        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }

        $whole = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($whole > 0 && strncmp($ipBin, $netBin, $whole) !== 0) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $remainder)) - 1) & 0xff;

        return (ord($ipBin[$whole]) & $mask) === (ord($netBin[$whole]) & $mask);
    }

    /** @return array{ok: bool, url: ?string, host: ?string, ips: array<int, string>, reason: string} */
    private static function fail(string $reason): array
    {
        return ['ok' => false, 'url' => null, 'host' => null, 'ips' => [], 'reason' => $reason];
    }
}
