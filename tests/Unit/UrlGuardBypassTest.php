<?php

namespace Tests\Unit;

use App\Services\AI\UrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bypasses found by an adversarial review of the first version of UrlGuard, kept
 * as tests so they cannot come back.
 *
 * Each one is a case where the guard said yes and the connection would have gone
 * somewhere else, or where PHP's own filter flags call an address "public" that
 * absolutely must not be reachable.
 */
class UrlGuardBypassTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ai.allow_local_providers', false);
    }

    /**
     * The one that mattered most.
     *
     * libcurl applies UTS-46 to a hostname before its own lookup, so
     * "ⓛocalhost.example.com" (U+24DB) becomes "localhost.example.com". The
     * first version resolved the raw bytes — which an attacker's wildcard record
     * answers with a public address — and curl then connected to an entirely
     * different name the attacker pointed wherever they liked. The guard
     * approved one host and the socket visited another.
     *
     * Asserted as an invariant rather than by staging attacker DNS: whatever
     * host the guard hands back must already be what curl would resolve, so
     * applying curl's mapping to it changes nothing. That holds without a
     * network and fails on any host the guard did not normalise.
     */
    #[DataProvider('hostsCurlRewrites')]
    public function test_the_host_is_normalised_to_exactly_what_curl_will_look_up(string $host): void
    {
        $approved = UrlGuard::normaliseHost($host);

        $this->assertNotNull($approved, "guard must not pass through an unnormalisable host: {$host}");

        // The defect, stated as an equality: whatever the guard decided to
        // resolve must survive curl's own UTS-46 mapping unchanged. The first
        // version returned the raw bytes, so this differed and the socket went
        // somewhere the guard had never checked.
        $this->assertSame(
            $approved,
            idn_to_ascii($approved, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46),
            "guard would resolve '{$approved}' but curl would look up something else",
        );

        // And it must be pure ASCII, since a non-ASCII byte is what starts the
        // divergence in the first place.
        $this->assertSame($approved, filter_var($approved, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_HIGH));
    }

    public function test_the_specific_bypass_that_was_found(): void
    {
        // U+24DB maps to plain "l", turning this into localhost.example.com —
        // a name the attacker points wherever they like, while the guard was
        // resolving (and approving) the raw-byte form via a wildcard record.
        $this->assertSame(
            'localhost.example.com',
            UrlGuard::normaliseHost("\u{24DB}ocalhost.example.com"),
        );
    }

    /** Without intl we cannot predict curl's mapping, so we must refuse. */
    public function test_an_unnormalisable_host_is_refused_rather_than_guessed_at(): void
    {
        $this->assertNull(UrlGuard::normaliseHost("\u{FFFD}\u{FFFD}.example.com"));
        $this->assertNull(UrlGuard::normaliseHost(''));
        $this->assertNull(UrlGuard::normaliseHost('[not-an-ip]'));
    }

    /**
     * Directly: the raw non-ASCII bytes must never appear in what the guard
     * reports back, because that string is what reaches the HTTP client.
     */
    public function test_raw_non_ascii_bytes_never_reach_the_client(): void
    {
        foreach (["\u{24DB}ocalhost.example.com", "local\u{00AD}host.example.com"] as $host) {
            $result = UrlGuard::check("https://{$host}/v1");

            $this->assertStringNotContainsString("\u{24DB}", (string) $result['host']);
            $this->assertStringNotContainsString("\u{00AD}", (string) $result['host']);
            $this->assertStringNotContainsString("\u{24DB}", (string) $result['url']);
            $this->assertStringNotContainsString("\u{00AD}", (string) $result['url']);
        }
    }

    /**
     * Hosts whose raw bytes differ from what curl will actually look up:
     * exactly the divergence the normalisation exists to close.
     *
     * @return array<string, array{string}>
     */
    public static function hostsCurlRewrites(): array
    {
        return [
            // U+24DB circled "l" maps to plain "l": the original bypass.
            'circled letter' => ["\u{24DB}ocalhost.example.com"],
            // Fullwidth letters map to their ASCII forms.
            'fullwidth letters' => ["\u{FF41}pi.example.com"],
            // Case-folding applies before the confusable mapping.
            'uppercase circled letter' => ["\u{24C1}OCALHOST.EXAMPLE.COM"],
            // A genuine IDN becomes stable ASCII punycode.
            'true idn' => ["b\u{00FC}cher.example.com"],
            // Plain ASCII still normalises: curl looks up lowercase.
            'ascii uppercase' => ['API.Example.COM'],
        ];
    }

    public static function rangesPhpCallsPublic(): array
    {
        return [
            // PHP's NO_PRIV_RANGE|NO_RES_RANGE flags accept every one of these.
            'Alibaba Cloud metadata' => ['https://100.100.100.200/latest/meta-data/'],
            'Oracle Cloud Classic metadata' => ['https://192.0.0.192/v1'],
            'CGNAT, RFC 6598' => ['https://100.64.1.1/v1'],
            'benchmarking, RFC 2544' => ['https://198.18.0.1/v1'],
            'multicast' => ['https://224.0.0.1/v1'],
            'TEST-NET-1' => ['https://192.0.2.1/v1'],
            'TEST-NET-2' => ['https://198.51.100.1/v1'],
            'TEST-NET-3' => ['https://203.0.113.1/v1'],
            // 64:ff9b::7f00:1 is 127.0.0.1 seen through NAT64.
            'NAT64 mapping of loopback' => ['https://[64:ff9b::7f00:1]/v1'],
            'NAT64 mapping of link-local' => ['https://[64:ff9b::a9fe:a9fe]/v1'],
            'IPv6 documentation' => ['https://[2001:db8::1]/v1'],
            'IPv6 multicast' => ['https://[ff02::1]/v1'],
        ];
    }

    #[DataProvider('rangesPhpCallsPublic')]
    public function test_it_refuses_ranges_php_filter_flags_wrongly_accept(string $url): void
    {
        $this->assertFalse(UrlGuard::allows($url), "{$url} must not be reachable");
    }

    /**
     * The addresses come back so the caller can pin the connection to them. If
     * check() stopped returning them the drivers would silently fall back to
     * letting curl re-resolve, reopening the rebinding window with no test
     * failing anywhere.
     */
    public function test_it_returns_the_addresses_it_approved(): void
    {
        $result = UrlGuard::check('https://1.1.1.1/v1');

        $this->assertTrue($result['ok']);
        $this->assertSame('1.1.1.1', $result['host']);
        $this->assertSame(['1.1.1.1'], $result['ips']);
    }

    /** A bracket that does not wrap a valid address is malformed, not a name. */
    public function test_malformed_brackets_are_refused(): void
    {
        $this->assertFalse(UrlGuard::allows('https://[not-an-ip]/v1'));
        $this->assertFalse(UrlGuard::allows('https://[[169.254.169.254]]/v1'));
    }

    /** Still-good behaviour from the first version, kept under this suite too. */
    public function test_a_genuinely_public_address_is_still_allowed(): void
    {
        $this->assertTrue(UrlGuard::allows('https://1.1.1.1/v1'));
        $this->assertTrue(UrlGuard::allows('https://8.8.8.8:8443/openai/v1'));
    }
}
