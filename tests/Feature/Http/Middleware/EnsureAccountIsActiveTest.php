<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deactivation used to gate exactly one thing — message delivery — so a
 * "deactivated" account could still sign in, publish, comment, like and review.
 * This middleware is what turned the flag into "no session", and it runs in the
 * `web` group rather than inside `auth`, so it also covers routes that are
 * merely user-aware.
 *
 * `appearance.edit` is the probe route throughout: a `Route::inertia()` page
 * behind `auth` with no controller and no query of its own, which is the shape
 * the starter kit's `dashboard` had before it was removed (2026-09-06). It is
 * deliberately **not** `profile.edit` — that controller calls
 * `loadMissing('media')` on the acting user, and `actingAs()` hands both arms
 * of the "costs no query" comparison the same model instance, so the second
 * request would find the relation already loaded and count one query fewer for
 * a reason that has nothing to do with the middleware.
 */
test('signs a deactivated account out and returns it to the login page', function () {
    $deactivated = User::factory()->inactive()->create();

    $this->actingAs($deactivated)
        ->get(route('appearance.edit'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your account has been deactivated.');

    $this->assertGuest();
});

/**
 * Nothing of the session may survive it: the same three calls an explicit
 * logout makes, so a token or a flash left over from before the deactivation
 * cannot be replayed.
 */
test('invalidates the session and regenerates the csrf token', function () {
    $deactivated = User::factory()->inactive()->create();

    $this->actingAs($deactivated)
        ->withSession(['_token' => 'token-from-before', 'checkout.step' => 'payment'])
        ->get(route('appearance.edit'))
        ->assertSessionMissing('checkout.step');

    expect(session('_token'))->not->toBe('token-from-before');
});

/**
 * Fortify's credential check is deliberately left alone — overriding it would
 * mean reimplementing `fortify.lowercase_usernames`, `Fortify::username()` and
 * rehash-on-login, and it still would not cover the passkey route. So a
 * deactivated sign-in authenticates, and the very next request ends it.
 */
test('lets a deactivated sign-in authenticate and ends it on the next request', function () {
    $deactivated = User::factory()->inactive()->create();

    $this->post(route('login'), ['email' => $deactivated->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($deactivated);

    $this->get(route('appearance.edit'))->assertRedirect(route('login'));

    $this->assertGuest();
});

/**
 * The bell menu and the inbox fetch with XHR, so a redirect to an HTML login
 * page would arrive as an unparseable body rather than a refusal.
 */
test('returns 403 to a json request from a deactivated account', function () {
    $deactivated = User::factory()->inactive()->create();

    $this->actingAs($deactivated)
        ->getJson(route('notifications.index'))
        ->assertForbidden()
        ->assertJsonPath('message', 'Your account has been deactivated.');
});

test('lets an active account through', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('appearance.edit'))
        ->assertOk();

    $this->assertAuthenticated();
});

test('lets a guest through to a public page', function () {
    $this->get(route('home'))->assertOk();
});

/**
 * Nothing earlier in the `web` group resolves the authenticated user, so this
 * middleware is in fact the **first** thing to call `$request->user()` and it
 * is what triggers the guard's lookup. That lookup is memoized on the guard and
 * HandleInertiaRequests shares the same user into every response, so the query
 * was always going to be issued — the middleware only moves it earlier. Once it
 * has the row, `is_active` is a column already on it, so the check is free.
 * Measured against the same request with the middleware removed rather than
 * against a fixed number, which would drift with whatever else the page loads.
 */
test('costs no query', function () {
    $user = User::factory()->create();

    $withMiddleware = countActiveGuardQueries(fn () => $this->actingAs($user)->get(route('appearance.edit'))->assertOk());

    $withoutMiddleware = countActiveGuardQueries(fn () => $this->actingAs($user)
        ->withoutMiddleware(EnsureAccountIsActive::class)
        ->get(route('appearance.edit'))
        ->assertOk());

    expect($withMiddleware)->toBe($withoutMiddleware);
});

/**
 * Count the queries one request issues, so the guard can be priced against the
 * same request without it.
 */
function countActiveGuardQueries(Closure $request): int
{
    $count = 0;

    DB::listen(function () use (&$count): void {
        $count++;
    });

    $request();

    return $count;
}
