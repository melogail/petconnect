<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * There is no dashboard, and this file is what keeps it that way.
 *
 * The starter kit shipped `Route::inertia('dashboard', 'Dashboard')` — a grid
 * of placeholder patterns inside the sidebar shell — and pointed `fortify.home`
 * at it, so every sign-in, registration and verification landed on a page with
 * nothing on it. Both were removed on the user's instruction (2026-09-06)
 * together with the sidebar shell; the legacy app never had a dashboard and
 * landed a fresh sign-in on the feed.
 *
 * Two things can quietly bring it back and neither is a compile error: a
 * scaffold re-run re-adding the route, or a config merge restoring
 * `'home' => '/dashboard'`, which would send every login to a 404. So the
 * absence of the route and the target of the redirect are pinned here, by
 * name, rather than left to the auth tests that only assert the redirect they
 * happen to get.
 */
test('the dashboard route is gone and its path is a 404 even when signed in', function () {
    expect(Route::has('dashboard'))->toBeFalse();

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertNotFound();
});

/**
 * `fortify.home` is where a login, a registration, a verification and a
 * password reset all land. It is asserted against the named route rather than
 * the literal `/` so a future prefix on the feed moves both together.
 */
test('a successful sign-in lands on the feed', function () {
    expect(config('fortify.home'))->toBe(route('home', absolute: false));

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('home', absolute: false));
});
