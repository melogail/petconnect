---
paths:
  - 'app/Http/Middleware/**'
---

# Middleware

## Appended web middleware do NOT run before route middleware — $middlewarePriority reorders them
bootstrap/app.php and EnsureAccountIsActive both used to claim the appended `web` group entries "run before route middleware, so EnsureAccountIsActive fires ahead of auth and verified". False. `Kernel::$middlewarePriority` is applied to the whole gathered stack and names Authenticate, ThrottleRequests, SubstituteBindings and EnsureEmailIsVerified, so those get pulled ahead of every unlisted entry.

Measured on `pets.store` (`['auth','verified']`): EncryptCookies, AddQueuedCookiesToResponse, StartSession, ShareErrorsFromSession, PreventRequestForgery, **Authenticate**, ThrottleRequests, SubstituteBindings, **EnsureAccountIsActive**, SetLocale, HandleAppearance, HandleInertiaRequests, AddLinkHeaders, InjectBoost, **EnsureEmailIsVerified**.

The outcome was never wrong — EnsureAccountIsActive still runs before `verified`, before HandleInertiaRequests and before any controller — but the reasoning is what somebody reuses when adding the next entry. Check `Route::gatherRouteMiddleware()` on a real route rather than reading the append order. One knock-on worth knowing: on a guarded route `Authenticate` resolves the user first, so EnsureAccountIsActive is no longer "the first thing to call $request->user()" (it still is on a public route).

## ThrottleAuthRoutes runs ahead of the route's guest — documented, not fixed
ThrottleAuthRoutes is attached to the package middleware *group* (`config('fortify.middleware')`, `config('nova.middleware')`), so it runs before `RedirectIfAuthenticated`. An already-authenticated caller POSTing to `register`, `forgot-password` or `reset-password` therefore has the hit recorded against their IP (`RateLimitServiceProvider::unauthenticatedCallerKey()`) rather than the user id `rateLimitKey()` would give.

This is a real asymmetry with **no consequence** and it is deliberately left as is: whichever bucket the hit lands in, the caller gets the same 302 and never reaches a controller. Do not "fix" it by reordering middleware, moving the throttle onto the route, or asking the guard inside the key helper — each changes which bucket *real* traffic is counted in, to tidy a case that cannot succeed. Recorded so the next person to rediscover it stops at noticing.

## prependToPriorityList anchors on the AuthenticatesRequests contract, not on Authenticate
Measured 2026-10-26 while wiring the docs host (`routes/docs.php`). `$middlewarePriority` lists `Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests`, not `Illuminate\Auth\Middleware\Authenticate`. `prependToPriorityList(Authenticate::class, X::class)` finds no anchor and X lands at the **end** of the priority list — sorted after every auth middleware, the opposite of what was asked, with no warning. Anchor on the contract. Subclasses such as `Laravel\Nova\Http\Middleware\Authenticate` sort as the contract too, because `SortedMiddleware` matches class parents and interfaces.

Why it matters: an exception thrown by `Authenticate` is rendered at that layer and the redirect flows back out through the *outer* middleware only. A middleware that must stamp a header on the guest's login redirect (`App\Http\Middleware\DisallowRobots`) has to sort ahead of auth, which only the priority list can arrange. `tests/Feature/Http/DocsSiteTest.php` pins the header on the redirect.

Second trap from the same change: a `Route::domain()` catch-all (`{path?}` with `.*`) in a routes file is matched before package routes that carry no domain (Nova's), even though it is registered after them. Carve the package's prefixes out of the pattern (`^(?!nova(?:$|[/-])).*$`) or the guest redirect to `/nova/login` loops on that host.

## The shared session leaks `url.intended` across hosts: the public login must scrub it, Nova must not
`SESSION_DOMAIN` is the parent domain, so a logged-out visit to `nova.<host>/…` stores a Nova URL as `url.intended` in the same session the public site reads. Every Fortify, passkey and social response then does `redirect()->intended()`, and for an Inertia XHR a 302 to another origin is a CORS failure the browser log shows as `HttpNetworkError Network error (…/login)`: the member *is* logged in, the form just looks dead. Reproduced with curl (Nova visit → `POST /login` → `302 nova.<host>/dashboards/main`), 2026-10-30.

`ForgetForeignIntendedUrl` (appended to `web`) forgets a foreign-host intended URL **only when the request host is `APP_URL`'s host**. Do not widen it to every host: Nova answers its login with a JSON `redirect` the client follows by full navigation, and `routes/docs.php` relies on exactly that cross-host intended URL to send an admin back to the docs after the Nova login (verified: Nova login after a docs visit returns `{"redirect":"https://docs.<host>/guide"}`). `tests/Feature/Auth/AuthenticationTest.php` pins both directions; the foreign-host test was run against the middleware disabled and failed.
