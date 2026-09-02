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
 * routes/web.php. These six cannot, because this application does not declare
 * them: Fortify registers its routes from `vendor/laravel/fortify/routes/
 * routes.php` and Nova from `Laravel\Nova\PendingRouteRegistration`, and
 * neither package exposes a hook for the ones below.
 *
 * - `config('fortify.limiters')` has exactly three slots — `login`,
 *   `two-factor` and `passkeys` — and Fortify's routes file reads only those.
 *   There is no `confirmPassword`, `registration` or `resetPassword` key to
 *   fill in.
 * - Nova reads `fortify.limiters.login`, `.passkeys`, `.two-factor` and
 *   `fortify.limiters.verification` for its own copies of those routes, and
 *   registers `nova.password.confirm` and `nova.password.reset` with a
 *   middleware group and nothing else.
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
 * route group, and `config('nova.middleware')`. This class is the entry in
 * both, and it no-ops for every route name that is not in the map — the cost on
 * everything else is one array lookup.
 *
 * The Nova half moved from `api_middleware` to `middleware` when
 * `nova.password.reset` was added to the map, and the reason is Nova's group
 * topology rather than taste. NovaCoreServiceProvider builds three groups:
 * `nova` from `config('nova.middleware')`, `nova:api` from
 * `config('nova.api_middleware')` (whose first entry is `nova`), and `nova:auth`
 * from `config('nova.middleware')` plus RedirectIfAuthenticated. Nova's
 * password reset and login routes are registered with `nova:auth`, which
 * `api_middleware` never reaches, while everything `api_middleware` covers is
 * reached through its own `nova` entry. One listing in `middleware` therefore
 * covers both; listing it in both places would be redundant rather than
 * harmful, since Router::uniqueMiddleware() collapses the duplicate.
 *
 * ## It delegates rather than reimplements
 *
 * `Illuminate\Routing\Middleware\ThrottleRequests` already knows how to resolve
 * a named limiter, hash the key, return 429 and attach `Retry-After` and the
 * `X-RateLimit-*` headers. Calling it with exactly three arguments is what
 * selects its named-limiter path (it branches on `func_num_args() === 3`), so
 * these routes behave exactly like a route declared `->middleware('throttle:x')`
 * in routes/web.php, and a limiter defined here is defined the same way as
 * every other one in AppServiceProvider::configureRateLimiters().
 *
 * ## The map keys on route names, so an unnamed route cannot be entered in it
 *
 * `$route->getName()` is null for a route registered without `->name(...)`, and
 * this class returns early on a null name, so an entry for an unnamed route is
 * dead code that fails silently — no exception, no 429, nothing in a log, and a
 * test written against the same assumption would have to hit the URI to notice.
 * Nothing is exposed by this today: all four of Fortify's routes here are named
 * and 107 of Nova's 110 routes are, the three exceptions being the
 * `nova-vendor/ebess/advanced-nova-media-library/*` media routes, which are not
 * credential flows. It is recorded because the next person adding a Nova entry
 * has no way to tell from this file that the route they picked has to be named.
 * If one ever is not, the throttle has to go on the URI instead — `$request->is()`
 * in this class, or the package's own registration — and that is a different
 * change from adding a line to the map.
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
 * - `password.update` / `nova.password.reset` — POST `reset-password` and POST
 *   `nova/password/reset`, the submit half of the flow whose request half
 *   `password.email` guards. Throttling the link and not the submission left
 *   the half that pays out a credential open, from any address, as often as the
 *   caller likes.
 *
 *   Do not overstate the per-request cost, because the next reviewer will check
 *   it and delete the limiter when the claim does not hold. A rejected
 *   submission is one indexed `users` lookup, one primary-key lookup on
 *   `password_reset_tokens` (`email` is that table's primary key, so
 *   `DatabaseTokenRepository::exists()` reads one row, not every unexpired one)
 *   and at most one bcrypt, since `exists()` short-circuits before
 *   `hasher->check()` on a missing or expired row. It is **not** a
 *   `Password::defaults()` run and therefore not an `uncompromised()` network
 *   call: Fortify's `NewPasswordController::store()` validates only `token`,
 *   `email` and `password` as `required`, and the defaults run inside
 *   `ResetUserPassword::reset()`, which `PasswordBroker::reset()` invokes only
 *   after the token has already validated. Nova's controller subclasses
 *   Fortify's and inherits that.
 *
 *   What is worth throttling is not that arithmetic. It is that this is the
 *   submit half of a credential flow — a hit sets a password, and Nova's copy
 *   sets an **admin** one — and that `PasswordBroker::reset()` runs inside a
 *   200 ms Timebox that a failure does not return early from, so each rejected
 *   POST holds a PHP worker for a fifth of a second for an unauthenticated
 *   caller with no session and no cookie. Token brute force was never the
 *   realistic attack: `createNewToken()` is `hash_hmac('sha256',
 *   Str::random(40), $hashKey)`, a 64-character *hex* string stored hashed
 *   again in a row that expires in an hour.
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
     * Every key must be a route **name**; see the class docblock for why an
     * entry for an unnamed route silently does nothing.
     *
     * @var array<string, string>
     */
    private const LIMITERS = [
        'password.confirm.store' => 'password-confirmations',
        'nova.password.confirm' => 'password-confirmations',
        'register.store' => 'registrations',
        'password.email' => 'password-reset-links',
        'password.update' => 'password-resets',
        'nova.password.reset' => 'password-resets',
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
