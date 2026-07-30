<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureDemoIsReadOnly;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        $middleware->web(append: EnsureAccountActive::class);

        $middleware->web(append: [
            SecurityHeaders::class,
            // Prepended would run before the session is started, and the guest
            // language choice lives in the session.
            SetLocale::class,
            // Applied to the whole web group so every future POST route is
            // demo-proof by default rather than by remembering to add it.
            EnsureDemoIsReadOnly::class,
        ]);

        // Behind a load balancer every request appears to come from the proxy,
        // so throttle:6,1 on sync and throttle:10,1 on AI would be shared by the
        // whole internet rather than applied per client. Configured via
        // TRUSTED_PROXIES so it is a deployment decision, not a code change.
        $proxies = env('TRUSTED_PROXIES');
        if (filled($proxies)) {
            $middleware->trustProxies(
                at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)),
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Never flash a secret back into the session.
        //
        // On a validation failure Laravel redirects with the old input so the
        // form can be repopulated, and its default exclusion list covers only
        // password fields. An AI provider key submitted alongside a base URL the
        // SSRF guard rejects — precisely the case that fails most often — would
        // otherwise be written to the sessions table in plaintext, next to the
        // ai_credentials row whose entire purpose is that it is encrypted, and
        // sit there for the session lifetime. hevy_api_key is listed for the
        // same reason on the profile form.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'api_key',
            'hevy_api_key',
        ]);
    })->create();
