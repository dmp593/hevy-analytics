<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Everything the app knows about which language to speak.
 *
 * Centralised so the middleware, the switcher, the profile form and validation
 * cannot disagree about what is supported — the same class of drift that once
 * left a Blade view gating on a stale copy of a service's status list.
 */
class Locales
{
    /** @return array<string, array{native: string, regional: string}> */
    public static function supported(): array
    {
        return config('locales.supported', []);
    }

    /** @return array<int, string> */
    public static function codes(): array
    {
        return array_keys(self::supported());
    }

    public static function fallback(): string
    {
        return config('locales.fallback', 'en');
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::supported());
    }

    /** The language's own name, for the switcher. */
    public static function nativeName(string $locale): string
    {
        return self::supported()[$locale]['native'] ?? $locale;
    }

    /**
     * The regional variant used for dates and numbers.
     *
     * Distinct from the interface locale on purpose: "pt" chooses the
     * translation files, "pt_PT" decides that a decimal comma is a comma and
     * that 26/07 is not the 7th of the 26th month.
     */
    public static function regional(string $locale): string
    {
        return self::supported()[$locale]['regional'] ?? $locale;
    }

    /**
     * Best supported match for what the browser asked for.
     *
     * Accept-Language carries quality weights and region subtags ("pt-BR;q=0.9"),
     * so match on the primary subtag and honour the ordering the browser gave.
     */
    public static function fromRequest(Request $request): ?string
    {
        $header = $request->header('Accept-Language');
        if (! $header) {
            return null;
        }

        $candidates = [];
        foreach (explode(',', $header) as $part) {
            $bits = explode(';q=', trim($part));
            $tag = strtolower(trim($bits[0]));
            if ($tag === '' || $tag === '*') {
                continue;
            }
            $candidates[] = ['tag' => $tag, 'q' => isset($bits[1]) ? (float) $bits[1] : 1.0];
        }

        usort($candidates, fn ($a, $b) => $b['q'] <=> $a['q']);

        foreach ($candidates as $candidate) {
            $primary = explode('-', $candidate['tag'])[0];
            if (self::isSupported($primary)) {
                return $primary;
            }
        }

        return null;
    }
}
