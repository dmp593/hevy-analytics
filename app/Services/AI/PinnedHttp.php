<?php

namespace App\Services\AI;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * An HTTP client pinned to the addresses UrlGuard actually approved.
 *
 * Validating a hostname and then handing the hostname to the HTTP client leaves
 * the client free to look it up again. Between the guard's lookup and the
 * socket's lookup an attacker serving a zero-TTL record can change the answer:
 * the guard sees a public address and approves, the socket sees 127.0.0.1 and
 * connects. That is DNS rebinding, and re-running the guard more often does not
 * fix it — it just adds another lookup that can also be raced.
 *
 * The fix is to stop asking twice. curl's CURLOPT_RESOLVE pre-seeds its resolver
 * cache for one host:port, so the connection goes to the address the guard
 * checked and no second lookup happens at all. TLS verification still runs
 * against the hostname, so pinning the address does not weaken certificate
 * checking — a pinned address serving the wrong certificate still fails.
 */
class PinnedHttp
{
    /**
     * @param  array<int, string>  $ips  Addresses UrlGuard approved for this host.
     */
    public static function to(string $baseUrl, string $host, array $ips): PendingRequest
    {
        $port = parse_url($baseUrl, PHP_URL_PORT)
            ?? (str_starts_with($baseUrl, 'http://') ? 80 : 443);

        // An IP literal needs no pinning: there was never a name to re-resolve.
        if ($ips === [] || filter_var($host, FILTER_VALIDATE_IP)) {
            return Http::baseUrl(rtrim($baseUrl, '/'));
        }

        // Bracket IPv6 addresses; curl requires that form in a RESOLVE entry.
        $addresses = implode(',', array_map(
            fn (string $ip) => str_contains($ip, ':') ? '['.$ip.']' : $ip,
            $ips,
        ));

        return Http::baseUrl(rtrim($baseUrl, '/'))->withOptions([
            'curl' => [
                CURLOPT_RESOLVE => ["{$host}:{$port}:{$addresses}"],
            ],
        ]);
    }
}
