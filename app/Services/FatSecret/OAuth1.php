<?php

namespace App\Services\FatSecret;

/**
 * OAuth 1.0a request signing (HMAC-SHA1), the only scheme FatSecret's
 * three-legged flow speaks.
 *
 * Kept as a small pure class so the signature algorithm can be pinned against
 * the RFC 5849 worked example in tests — a home-grown signer that was never
 * checked against external truth is how "invalid signature" support threads
 * are born. Nonce and timestamp are injectable for exactly that reason.
 */
final class OAuth1
{
    public function __construct(
        private readonly string $consumerKey,
        private readonly string $consumerSecret,
    ) {}

    /**
     * @param  array<string, string>  $params  query/body parameters (unencoded)
     * @return array<string, string> the same params plus every oauth_* field
     */
    public function sign(
        string $method,
        string $url,
        array $params,
        ?string $token = null,
        ?string $tokenSecret = null,
        ?string $nonce = null,
        ?int $timestamp = null,
    ): array {
        $oauth = [
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) ($timestamp ?? time()),
            'oauth_nonce' => $nonce ?? bin2hex(random_bytes(16)),
            'oauth_version' => '1.0',
        ];

        if ($token !== null) {
            $oauth['oauth_token'] = $token;
        }

        $all = $params + $oauth;

        // RFC 5849 §3.4.1.3.2: percent-encode pairs, sort by encoded key then
        // encoded value, join with & and = — rawurlencode is the RFC 3986
        // encoding PHP ships.
        $pairs = [];

        foreach ($all as $key => $value) {
            $pairs[] = [rawurlencode((string) $key), rawurlencode((string) $value)];
        }

        usort($pairs, fn ($a, $b) => $a[0] === $b[0] ? strcmp($a[1], $b[1]) : strcmp($a[0], $b[0]));

        $paramString = implode('&', array_map(fn ($p) => $p[0].'='.$p[1], $pairs));

        $base = strtoupper($method)
            .'&'.rawurlencode($this->baseUrl($url))
            .'&'.rawurlencode($paramString);

        $key = rawurlencode($this->consumerSecret).'&'.rawurlencode($tokenSecret ?? '');

        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base, $key, true));

        return $params + $oauth;
    }

    /** Scheme+host+path, lowercased scheme/host, default ports dropped (§3.4.1.2). */
    private function baseUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = mb_strtolower($parts['scheme'] ?? 'https');
        $host = mb_strtolower($parts['host'] ?? '');
        $port = $parts['port'] ?? null;

        $portPart = ($port !== null && ! in_array([$scheme, $port], [['http', 80], ['https', 443]], true))
            ? ':'.$port
            : '';

        return $scheme.'://'.$host.$portPart.($parts['path'] ?? '');
    }
}
