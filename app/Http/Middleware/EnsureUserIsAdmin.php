<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the admin area.
 *
 * 404 rather than 403 on purpose: a 403 confirms the route exists and that the
 * account simply lacks the flag, which is a small piece of free reconnaissance.
 * Someone who is not an admin should not learn that there is an admin area.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin, 404);

        return $next($request);
    }
}
