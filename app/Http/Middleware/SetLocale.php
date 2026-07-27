<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides which language the response is written in.
 *
 * Order of preference, most explicit first:
 *   1. what the signed-in user chose in their profile
 *   2. what a guest chose from the switcher (session)
 *   3. what their browser asked for (Accept-Language)
 *   4. the configured fallback
 *
 * Carbon is set too, otherwise "3 days ago" under every chart stays in English
 * while the page around it is translated — the kind of half-finished
 * localisation that reads worse than none at all.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        App::setLocale($locale);
        Carbon::setLocale($locale);

        // Regional formatting is a separate question from which translation
        // files to load: pt selects the strings, pt_PT decides that a decimal
        // separator is a comma.
        setlocale(LC_TIME, Locales::regional($locale));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $user = $request->user();
        if ($user && Locales::isSupported($user->locale)) {
            return $user->locale;
        }

        $session = $request->session()->get('locale');
        if (Locales::isSupported($session)) {
            return $session;
        }

        return Locales::fromRequest($request) ?? Locales::fallback();
    }
}
