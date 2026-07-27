<?php

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Language switching for people who are not signed in.
 *
 * A signed-in user's choice belongs on their profile and follows them between
 * devices; a guest has nowhere to store it but the session — and they need it,
 * because the login and registration pages are the first thing anyone sees.
 */
class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Locales::isSupported($locale), 404);

        $request->session()->put('locale', $locale);

        // A signed-in user switching here should have it persist, not be
        // silently overridden by their profile on the next request.
        //
        // Except the demo: hundreds of visitors share that row, and persisting
        // one visitor's choice would flip the language for everyone after them
        // (the stored locale outranks the session in SetLocale). Their session
        // still carries the choice — but only while the shared account's own
        // locale stays null, so the seeder must never set it.
        if (($user = $request->user()) && ! $user->is_demo) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
