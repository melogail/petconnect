<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turn a deactivated account into a signed-out one.
 *
 * ## What deactivation means, settled here
 *
 * `users.is_active` used to gate exactly one thing — message delivery, through
 * User::acceptsMessagesFrom() — which meant a "deactivated" account could still
 * sign in, publish a listing, comment, like and review. That gap is recorded in
 * .ai/rules/app.md as something a later phase had to settle once. This is it.
 *
 * Deactivated now means, in full:
 *
 * 1. **No session.** Any authenticated request from a deactivated account is
 *    logged out here, the session is invalidated and the CSRF token is
 *    regenerated, exactly as an explicit logout would. This covers the session
 *    that already existed when the account was deactivated.
 * 2. **No usable sign-in.** A password or passkey login still authenticates —
 *    Fortify's credential check is left alone — but the very next request is
 *    this middleware, which ends the session and returns the account to the
 *    login page carrying the reason. Enforcing it here rather than in
 *    `Fortify::authenticateUsing()` is deliberate: that hook replaces the
 *    guard's whole credential check, so an application-level rule bolted onto
 *    it also has to reimplement `fortify.lowercase_usernames`,
 *    `Fortify::username()` and the rehash-on-login behaviour, and it would
 *    still not cover the passkey route, which does not go through it. One
 *    check, on every authenticated request, covers every way in.
 * 3. **Not addressable by id, anywhere.** User::resolveRouteBinding() refuses
 *    to bind a deactivated account, so `profile.show` and `profile.like` are a
 *    404 before a controller runs, and so is every flow that resolves a user
 *    through App\Enums\Reviewable / Reportable::findVisibleOrFail(). That last
 *    part is the Phase 6 fix: the check used to live only in UserPolicy::view,
 *    which the reviews vertical never asks, so `GET reviews/user/{id}` returned
 *    the full list and `POST reviews/user/{id}` wrote a review about — and
 *    notified — an account the system had decided was not addressable.
 *    UserPolicy::view still refuses the profile and is what answers
 *    `Gate::allows('view', $deactivated)`; it is no longer the only enforcement
 *    point.
 * 4. **No incoming messages.** User::acceptsMessagesFrom(), unchanged.
 *
 * What it deliberately does *not* mean: the account's existing listings,
 * comments and the reviews it *wrote about other people* stay published. (The
 * reviews written *about* the deactivated account are a different thing and are
 * covered by point 3 — `reviews/user/{id}` names the account itself, so it is
 * an address the binding refuses. Nothing was unpublished to achieve that; the
 * one endpoint that reads them by that id stopped answering.) They are content
 * about pets and about other people, other users are still reading and
 * answering them, and retiring
 * them would need every listing query to join `users` — a cost on the busiest
 * read path in the application for a flag almost no row carries. Retiring
 * content is a moderation action with its own audit trail, and it belongs on
 * the Nova resource in Phase 3, not as a side effect of a boolean.
 *
 * ## Why a middleware and not a check in the auth guard
 *
 * The predicate itself lives in one place — User::isActive() — and this is its
 * only enforcement point for sessions. A middleware is the only thing that can
 * reach a session established *before* deactivation; a login-time check alone
 * would leave an already-signed-in user working normally until their session
 * expired, which for a moderator deactivating an abusive account is the whole
 * point.
 *
 * It adds no query, and the reason depends on the route. On anything behind
 * `auth`, `Illuminate\Auth\Middleware\Authenticate` has already resolved the
 * user and the guard memoizes it, so `$request->user()` here is free. On a
 * public route nothing else resolves one — StartSession,
 * ShareErrorsFromSession, PreventRequestForgery and SubstituteBindings all do
 * not — so this middleware is what triggers the lookup, and
 * HandleInertiaRequests shares the same memoized user into the response's props
 * a few entries later. Either way the row was always going to be read, and once
 * it is in hand `is_active` is a column on it: no relation, no second read.
 *
 * ## Where in the stack it actually runs
 *
 * Appended `web` middleware are written before route middleware but do not
 * necessarily *run* first: Kernel::$middlewarePriority is applied to the whole
 * gathered stack, and `Authenticate`, `ThrottleRequests` and
 * `SubstituteBindings` are all on it, so they are pulled ahead of every
 * unlisted entry. Measured on `pets.store` (`['auth', 'verified']`), this
 * middleware is **ninth**: after Authenticate, ThrottleRequests and
 * SubstituteBindings, before SetLocale, HandleInertiaRequests and
 * EnsureEmailIsVerified.
 *
 * That is the ordering the guarantees above depend on, and none of them need it
 * to precede `auth`: an earlier version of this docblock and of bootstrap/app.php
 * both claimed it did. What matters is that it runs before any controller,
 * before `verified`, and before any prop is built — which it does, on a guarded
 * route and a public one alike.
 *
 * Placed in the `web` group rather than inside `auth`, so it also covers routes
 * that are merely user-aware — the public feed, a public profile — where a
 * deactivated visitor would otherwise keep a usable session and keep being
 * treated as signed in.
 */
class EnsureAccountIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isActive()) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $message = __('Your account has been deactivated.');

        if ($request->expectsJson()) {
            abort(403, $message);
        }

        return redirect()->route('login')->with('status', $message);
    }
}
