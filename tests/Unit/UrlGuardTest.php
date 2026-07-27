<?php

namespace Tests\Unit;

use App\Services\AI\UrlGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The app makes an authenticated outbound request to a base URL the user typed.
 * That is server-side request forgery unless something stops it, so these tests
 * are the something.
 *
 * Only literal IPs and a couple of well-known public names are used, so the
 * suite does not depend on a DNS server being reachable or on any particular
 * record existing.
 */
class UrlGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ai.allow_local_providers', false);
    }

    public static function blockedUrls(): array
    {
        return [
            // The single most valuable SSRF target: on most cloud providers this
            // hands instance credentials to whatever asks.
            'cloud metadata' => ['https://169.254.169.254/latest/meta-data/'],
            'link-local' => ['https://169.254.10.1/v1'],
            'loopback v4' => ['https://127.0.0.1:8080/v1'],
            'loopback v6' => ['https://[::1]/v1'],
            'private 10/8' => ['https://10.0.0.5/v1'],
            'private 172.16/12' => ['https://172.16.4.4/v1'],
            'private 192.168/16' => ['https://192.168.1.1/v1'],
            'unique local v6' => ['https://[fd00::1]/v1'],
            'plain http' => ['http://api.openai.com/v1'],
            'no scheme' => ['api.openai.com/v1'],
            'empty' => [''],
            'whitespace' => ['   '],
            'credentials in url' => ['https://user:pass@api.openai.com/v1'],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function test_it_refuses_unsafe_or_unroutable_urls(string $url): void
    {
        $result = UrlGuard::check($url);

        $this->assertFalse($result['ok'], "{$url} should have been refused");
        $this->assertNotNull($result['reason']);
        $this->assertNull($result['url']);
    }

    public function test_it_allows_a_public_https_endpoint(): void
    {
        // A literal public address, so the test does not need working DNS.
        $result = UrlGuard::check('https://1.1.1.1/v1');

        $this->assertTrue($result['ok'], $result['reason'] ?? '');
        $this->assertSame('https://1.1.1.1/v1', $result['url']);
    }

    public function test_it_keeps_the_path_and_drops_query_and_fragment(): void
    {
        $result = UrlGuard::check('https://1.1.1.1:8443/openai/v1/?key=leak#frag');

        $this->assertTrue($result['ok']);
        $this->assertSame('https://1.1.1.1:8443/openai/v1', $result['url']);
    }

    public function test_a_trailing_slash_is_normalised_away(): void
    {
        $this->assertSame('https://1.1.1.1/v1', UrlGuard::check('https://1.1.1.1/v1/')['url']);
    }

    /**
     * Loopback is a genuine case for someone running a local model, but it must
     * be the operator's explicit decision, never the default.
     */
    public function test_loopback_is_refused_by_default_and_allowed_only_on_opt_in(): void
    {
        $this->assertFalse(UrlGuard::allows('http://127.0.0.1:11434/v1'));

        config()->set('ai.allow_local_providers', true);

        $this->assertTrue(UrlGuard::allows('http://127.0.0.1:11434/v1'));
        $this->assertTrue(UrlGuard::allows('https://[::1]:11434/v1'));
    }

    /**
     * Opting in to local providers must not also open the private network: a
     * self-hoster wants their own machine, not the whole subnet the app sits on,
     * and metadata endpoints live on link-local rather than loopback.
     */
    public function test_opting_into_local_does_not_open_the_private_network(): void
    {
        config()->set('ai.allow_local_providers', true);

        $this->assertFalse(UrlGuard::allows('http://169.254.169.254/latest/meta-data/'));
        $this->assertFalse(UrlGuard::allows('http://10.0.0.5/v1'));
        $this->assertFalse(UrlGuard::allows('http://192.168.1.1/v1'));
    }

    public function test_a_host_is_rejected_when_any_of_its_addresses_is_private(): void
    {
        // localhost conventionally resolves to 127.0.0.1 and/or ::1. Whichever
        // the resolver returns, every address must clear the check, so this is
        // refused while local providers are off.
        $this->assertFalse(UrlGuard::allows('https://localhost/v1'));
    }

    public function test_an_unresolvable_host_is_refused_rather_than_attempted(): void
    {
        $result = UrlGuard::check('https://this-host-should-not-exist.invalid/v1');

        $this->assertFalse($result['ok']);
    }
}
