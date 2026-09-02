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
ThrottleAuthRoutes is attached to the package middleware *group* (`config('fortify.middleware')`, `config('nova.middleware')`), so it runs before `RedirectIfAuthenticated`. An already-authenticated caller POSTing to `register`, `forgot-password` or `reset-password` therefore has the hit recorded against their IP (`AppServiceProvider::unauthenticatedCallerKey()`) rather than the user id `rateLimitKey()` would give.

This is a real asymmetry with **no consequence** and it is deliberately left as is: whichever bucket the hit lands in, the caller gets the same 302 and never reaches a controller. Do not "fix" it by reordering middleware, moving the throttle onto the route, or asking the guard inside the key helper — each changes which bucket *real* traffic is counted in, to tidy a case that cannot succeed. Recorded so the next person to rediscover it stops at noticing.
