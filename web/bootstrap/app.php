<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'staff' => \App\Http\Middleware\EnsureUserIsStaff::class,
            'member' => \App\Http\Middleware\EnsureUserIsMember::class,
            'idle' => \App\Http\Middleware\ExpireIdleApiToken::class,
        ]);

        // Hosted behind cPanel's own proxy, every visitor would otherwise arrive as 127.0.0.1 — the audit trail's
        // IP column and the sign-in throttle would both be worthless. Only the front proxy is trusted for that.
        $middleware->trustProxies(at: '*');

        // API requests never redirect to a web login route — they return JSON 401.
        $middleware->redirectGuestsTo(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson()
                ? null
                : route('login', absolute: false);
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
