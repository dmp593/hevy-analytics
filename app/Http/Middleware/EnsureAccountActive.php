<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A disabled account's live sessions end at the next request — disabling
 * only at login would leave an open tab working for days.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->disabled_at !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('app.admin.account_disabled')]);
        }

        return $next($request);
    }
}
