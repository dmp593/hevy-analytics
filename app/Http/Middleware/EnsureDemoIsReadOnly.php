<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The demonstration account cannot change anything.
 *
 * Enforced here, in one place, rather than sprinkled through controllers:
 * a demo that can be edited is a demo someone has already vandalised by the
 * time the second visitor arrives, and every new POST route added later would
 * be a new hole. Blocking all unsafe methods by default means a new feature is
 * born protected instead of born exposed.
 */
class EnsureDemoIsReadOnly
{
    /**
     * Routes a demo visitor may still POST to.
     *
     * Leaving must work or the visitor is trapped in the demo; switching
     * language only touches their own session, not the shared account.
     */
    private const ALLOWED = ['logout', 'demo.leave', 'locale.update'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->is_demo || $request->isMethodSafe()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return $next($request);
        }

        return back()->with('error', __('app.demo.read_only'));
    }
}
