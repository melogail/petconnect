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
 * 3. **No public profile.** UserPolicy::view refuses it — to everyone,
 *    including the account itself, because point 1 means the account can never
 *    be the viewer.
 * 4. **No incoming messages.** User::acceptsMessagesFrom(), unchanged.
 *
 * What it deliberately does *not* mean: the account's existing listings,
 * comments and reviews stay published. They are content about pets and about
 * other people, other users are still reading and answering them, and retiring
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
 * It adds no query — but not for the reason this used to give. Nothing in the
 * default `web` group resolves the authenticated user (StartSession,
 * ShareErrorsFromSession, ValidateCsrfToken, SubstituteBindings do not), and
 * this middleware is appended ahead of `auth`, so it is in fact the **first**
 * thing to call `$request->user()` and it is what triggers the guard's lookup.
 * The lookup is memoized on the guard, and HandleInertiaRequests two entries
 * later shares the same user into every response's props, so that one query was
 * always going to be issued and this middleware only moves it earlier. Once it
 * has the row, `is_active` is a column already on it — no relation, no second
 * read.
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
