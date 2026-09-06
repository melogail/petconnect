<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
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
        /*
         * `locale` joins the plaintext cookies for the same reason `appearance`
         * is there: the client reads it to decide the document's `lang` and
         * `dir` before the first Inertia payload arrives, and it holds no
         * secret — the whole value is `en` or `ar`. `sidebar_state` used to be
         * the third entry; it was the starter kit's sidebar cookie, and the
         * sidebar shell was removed on 2026-09-06 (resources/js/app.ts).
         */
        $middleware->encryptCookies(except: ['appearance', 'locale']);

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
         * HandleInertiaRequests, InjectBoost, **EnsureEmailIsVerified**.
         * (AddLinkHeadersForPreloadedAssets sat between HandleInertiaRequests
         * and InjectBoost when that was measured; see below for why it is gone.)
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
         *
         * `AddLinkHeadersForPreloadedAssets` is deliberately NOT appended, and
         * this is the fix for a 502 on every Valet/nginx machine, not a
         * preference. The starter kit appends it so the `<link rel=preload>`
         * tags Vite renders are mirrored into one `Link:` response header. On
         * this application that header is **4,310 bytes on `/`** and 2,679 on
         * `/help` (measured 2026-09-06 with `curl -D` against `artisan serve`,
         * fonts and every preloaded chunk listed), and with the two ~450-byte
         * session and XSRF cookies beside it the response headers overflow
         * nginx's default 4 KB `fastcgi_buffer_size`. nginx then answers
         * "502 Bad Gateway" and logs `upstream sent too big header while
         * reading response header from upstream` — measured as 9 of 10
         * requests to `/` through Valet, with the same error in
         * `~/.valet/Log/nginx-error.log` back to 2026-09-03. Nothing is lost by
         * dropping it: the preload tags stay in the HTML, only the duplicate
         * header goes. Raising `fastcgi_buffer_size` per machine would fix the
         * symptom on that machine and leave the next one broken.
         */
        $middleware->web(append: [
            EnsureAccountIsActive::class,
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
