<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * `locale` joins the plaintext cookies for the same reason `appearance`
         * and `sidebar_state` are there: the client reads it to decide the
         * document's `lang` and `dir` before the first Inertia payload arrives,
         * and it holds no secret — the whole value is `en` or `ar`.
         */
        $middleware->encryptCookies(except: ['appearance', 'locale', 'sidebar_state']);

        /*
         * Order matters here.
         *
         * `EnsureAccountIsActive` runs before HandleInertiaRequests, so a
         * deactivated account is signed out before any props are built for it.
         * Appended `web` middleware also run before route middleware, so it
         * fires ahead of `auth` and `verified` and no controller is reached.
         *
         * `SetLocale` runs after it, because logging a deactivated user out
         * changes whose locale preference applies; and before
         * HandleInertiaRequests, so the shared props and every `__()` in the
         * response are already in the resolved language.
         */
        $middleware->web(append: [
            EnsureAccountIsActive::class,
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
