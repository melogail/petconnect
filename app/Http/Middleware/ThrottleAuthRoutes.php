<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attach a named rate limiter to the auth routes Fortify and Nova register
 * without one.
 *
 * ## Why this class exists at all
 *
 * Every other throttled write in this application says so on its own route in
 * routes/web.php. These four cannot, because this application does not declare
 * them: Fortify registers its routes from `vendor/laravel/fortify/routes/
 * routes.php` and Nova from `Laravel\Nova\PendingRouteRegistration`, and
 * neither package exposes a hook for the ones below.
 *
 * - `config('fortify.limiters')` has exactly three slots — `login`,
 *   `two-factor` and `passkeys` — and Fortify's routes file reads only those.
 *   There is no `confirmPassword` or `registration` key to fill in.
 * - Nova reads `fortify.limiters.login`, `.passkeys`, `.two-factor` and
 *   `fortify.limiters.verification` for its own copies of those routes, and
 *   registers `nova.password.confirm` with `config('nova.api_middleware')` and
 *   nothing else.
 *
 * The three alternatives were each worse. Redeclaring the routes in
 * routes/web.php does not work: `Illuminate\Foundation\Support\Providers\
 * RouteServiceProvider` loads the application's routes from an `$app->booted()`
 * callback, so they are registered **after** both packages' and lose the match
 * (measured: Fortify's four sit at positions 4-18 of the collection). Mutating
 * the `Illuminate\Routing\Route` objects from a `booted()` callback of our own
 * is worse still — with `route:cache` on, `loadCachedRoutes()` queues the
 * require of the cache file as a *further* `booted()` callback, so our mutation
 * would run against an empty collection and silently do nothing, in production
 * only. And turning `Fortify::$registersRoutes` off means owning a copy of the
 * package's whole routes file.
 *
 * So the limiter is attached where the packages do give this application a
 * say: `config('fortify.middleware')`, which Fortify applies to its whole
 * route group, and `config('nova.api_middleware')`. This class is the entry in
 * both, and it no-ops for every route name that is not in the map — the cost on
 * everything else is one array lookup.
 *
 * ## It delegates rather than reimplements
 *
 * `Illuminate\Routing\Middleware\ThrottleRequests` already knows how to resolve
 * a named limiter, hash the key, return 429 and attach `Retry-After` and the
 * `X-RateLimit-*` headers. Calling it with exactly three arguments is what
 * selects its named-limiter path (it branches on `func_num_args() === 3`), so
 * these routes behave exactly like a route declared `->middleware('throttle:x')`
 * in routes/web.php, and a limiter defined here is defined the same way as the
 * eight in AppServiceProvider::configureRateLimiters().
 *
 * ## What each entry guards
 *
 * - `password.confirm.store` / `nova.password.confirm` — POST
 *   `user/confirm-password` and POST `nova/user-security/confirm-password`.
 *   Both were a clean yes/no password oracle behind a session cookie and
 *   nothing else: 50 wrong guesses returned 50 × 422 and never a 429. A
 *   successful confirmation then unlocks `settings/security`, the two-factor
 *   recovery codes, `two-factor.disable` and passkey registration for
 *   `config('auth.password_timeout')` seconds, and a registered passkey
 *   survives a later password change — so a borrowed session was a permanent
 *   takeover. The Nova copy pays out an admin password.
 * - `register.store` — POST `register` was bounded by nothing at all: 40 posts
 *   from one IP created 40 accounts and sent 40 verification emails to
 *   attacker-chosen addresses, which makes the application's mail domain an
 *   unauthenticated relay.
 * - `password.email` — POST `forgot-password`. `config('auth.passwords.users.
 *   throttle')` bounds resends **per email address**, so walking an address
 *   list was unbounded from a single caller, with the same mail-reputation
 *   blast radius aimed at people who never signed up.
 *
 * Nova's `nova.password.email` is deliberately absent: `admins` are created by
 * another admin, the address list is tiny and known, and no admin's inbox is a
 * stranger's. Add it here if that ever stops being true.
 */
class ThrottleAuthRoutes
{
    /**
     * Route name => the named rate limiter that should guard it.
     *
     * @var array<string, string>
     */
    private const LIMITERS = [
        'password.confirm.store' => 'password-confirmations',
        'nova.password.confirm' => 'password-confirmations',
        'register.store' => 'registrations',
        'password.email' => 'password-reset-links',
    ];

    public function __construct(private readonly ThrottleRequests $throttle) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $name = $route instanceof Route ? $route->getName() : null;
        $limiter = $name === null ? null : (self::LIMITERS[$name] ?? null);

        if ($limiter === null) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $limiter);
    }
}
