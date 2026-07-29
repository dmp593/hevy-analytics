<?php

namespace App\Services\FatSecret;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * The FatSecret Platform API, spoken through OAuth 1.0 — chosen over OAuth 2.0
 * deliberately: their OAuth 2.0 token endpoint requires an IP whitelist (with
 * 24 h propagation), which a free-tier host with shared egress cannot promise.
 *
 * Endpoints are config with documented defaults rather than constants: the
 * official docs and the ecosystem's older libraries disagree on the
 * authorize/access hosts, so a mismatch must be an env fix, not a deploy.
 */
class FatSecretClient
{
    private readonly OAuth1 $oauth;

    public function __construct()
    {
        $this->oauth = new OAuth1(
            (string) config('services.fatsecret.consumer_key'),
            (string) config('services.fatsecret.consumer_secret'),
        );
    }

    public static function configured(): bool
    {
        return filled(config('services.fatsecret.consumer_key'))
            && filled(config('services.fatsecret.consumer_secret'));
    }

    /** @return array{token: string, secret: string} */
    public function requestToken(string $callbackUrl): array
    {
        $url = config('services.fatsecret.request_token_url');
        $params = $this->oauth->sign('POST', $url, ['oauth_callback' => $callbackUrl]);

        return $this->tokenPair(Http::asForm()->post($url, $params)->throw()->body());
    }

    public function authorizeUrl(string $token): string
    {
        return config('services.fatsecret.authorize_url').'?oauth_token='.rawurlencode($token);
    }

    /** @return array{token: string, secret: string} */
    public function accessToken(string $token, string $tokenSecret, string $verifier): array
    {
        $url = config('services.fatsecret.access_token_url');
        $params = $this->oauth->sign('POST', $url, ['oauth_verifier' => $verifier], $token, $tokenSecret);

        return $this->tokenPair(Http::asForm()->post($url, $params)->throw()->body());
    }

    /**
     * The day's diary totals for a linked account, or null when the diary has
     * no entries that day.
     *
     * @return ?array{calories: int, protein: int, fat: int, carbs: int}
     */
    public function dayTotals(User $user, Carbon $day): ?array
    {
        $url = config('services.fatsecret.api_url');

        $params = $this->oauth->sign('GET', $url, [
            'method' => 'food_entries.get',
            // FatSecret dates are whole days since the Unix epoch.
            'date' => (string) intdiv($day->copy()->utc()->startOfDay()->getTimestamp(), 86400),
            'format' => 'json',
        ], $user->fatsecret_token, $user->fatsecret_secret);

        $json = Http::get($url, $params)->throw()->json();

        $entries = $json['food_entries']['food_entry'] ?? null;

        if ($entries === null) {
            return null;
        }

        // Their JSON collapses a single entry into an object instead of a list.
        if (array_is_list($entries) === false) {
            $entries = [$entries];
        }

        $totals = ['calories' => 0.0, 'protein' => 0.0, 'fat' => 0.0, 'carbs' => 0.0];

        foreach ($entries as $entry) {
            $totals['calories'] += (float) ($entry['calories'] ?? 0);
            $totals['protein'] += (float) ($entry['protein'] ?? 0);
            $totals['fat'] += (float) ($entry['fat'] ?? 0);
            $totals['carbs'] += (float) ($entry['carbohydrate'] ?? 0);
        }

        return array_map(fn ($v) => (int) round($v), $totals);
    }

    /** @return array{token: string, secret: string} */
    private function tokenPair(string $body): array
    {
        parse_str(trim($body), $parsed);

        if (blank($parsed['oauth_token'] ?? null) || blank($parsed['oauth_token_secret'] ?? null)) {
            throw new \RuntimeException('FatSecret did not return a token pair: '.mb_substr($body, 0, 200));
        }

        return ['token' => $parsed['oauth_token'], 'secret' => $parsed['oauth_token_secret']];
    }
}
