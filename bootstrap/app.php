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
         * Order matters here, and the order these are written in is not the
         * order they run in.
         *
         * Kernel::$middlewarePriority is applied to the whole gathered stack,
         * and it names `Authenticate`, `ThrottleRequests`, `SubstituteBindings`
         * and `EnsureEmailIsVerified` among others. SortedMiddleware pulls
         * those into their canonical relative order and leaves everything else
         * where it sits, so a route middleware from the priority list can end
         * up *ahead* of an appended group entry. Measured on `pets.store`
         * (`['auth', 'verified']`): EncryptCookies, AddQueuedCookiesToResponse,
         * StartSession, ShareErrorsFromSession, PreventRequestForgery,
         * **Authenticate**, ThrottleRequests, SubstituteBindings,
         * **EnsureAccountIsActive**, SetLocale, HandleAppearance,
         * HandleInertiaRequests, AddLinkHeadersForPreloadedAssets, InjectBoost,
         * **EnsureEmailIsVerified**.
         *
         * So: `EnsureAccountIsActive` runs *after* `auth`, *before* `verified`,
         * and before HandleInertiaRequests — which is what matters. A
         * deactivated account is signed out before any props are built for it
         * and before any controller runs, on a guarded route and on a public
         * one alike. This comment used to claim it fired ahead of `auth`
         * because appended group middleware precede route middleware; the
         * outcome was right and the reason was wrong, and the reason is what
         * somebody would reuse when adding the next entry here.
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
