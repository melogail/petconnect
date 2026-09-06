<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

/*
 * The resolved locale is observable on every page through the `locale` prop
 * HandleInertiaRequests shares — `current` plus the `direction` the layouts put
 * on `dir` — and SetLocale runs ahead of it precisely so the props and every
 * `__()` in the response are already in the resolved language.
 */

test('falls back to the application language when nothing is recorded', function () {
    $this->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('locale.current', 'en')
            ->where('locale.direction', 'ltr'));
});

/**
 * A guest has no row to write to, so the cookie is the only thing that survives
 * their next visit.
 */
test('uses the cookie a guest carries', function () {
    $this->withUnencryptedCookie('locale', 'ar')
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('locale.current', 'ar')
            ->where('locale.direction', 'rtl'));
});

/**
 * The cookie is the most recent *explicit* act: switching language writes the
 * cookie and the user row together, so the two only disagree when the account
 * has since switched on a device whose cookie says otherwise. Honouring the
 * device is what somebody reading in a shared browser expects.
 */
test('prefers the cookie over the signed in account own language', function () {
    $this->actingAs(User::factory()->create(['locale' => 'en']))
        ->withUnencryptedCookie('locale', 'ar')
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale.current', 'ar'));
});

/**
 * Second in the order, so a signed-in account carries its language to a new
 * browser on the first request, before any cookie exists.
 */
test('uses the signed in account language when no cookie has been set', function () {
    $this->actingAs(User::factory()->create(['locale' => 'ar']))
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale.current', 'ar'));
});

/**
 * Last resort before the default, for a client that refuses cookies.
 */
test('uses the session when there is neither a cookie nor an account', function () {
    $this->withSession(['locale' => 'ar'])
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale.current', 'ar'));
});

/**
 * Every candidate is checked against `petconnect.locales.supported`. Without
 * that filter a hand-edited cookie would hand App::setLocale() an arbitrary
 * string and every `__()` on the page would return its own key.
 */
test('ignores a hand edited cookie naming an unsupported language', function () {
    $this->withUnencryptedCookie('locale', '../../etc/passwd')
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale.current', 'en'));
});

test('ignores an unsupported language stored on the account', function () {
    $this->actingAs(User::factory()->create(['locale' => 'de']))
        ->get(route('home'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('locale.current', 'en'));
});

/**
 * Read-only by design: it picks a locale from what is already recorded and hands
 * it to App::setLocale(). Writing is Actions\Profiles\ApplyUserLocale's, called
 * from LocaleController and from the profile update flow — one writer, one
 * reader.
 */
test('records nothing of its own', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->withUnencryptedCookie('locale', 'ar')
        ->get(route('home'))
        ->assertOk()
        ->assertCookieMissing('locale');

    expect($user->fresh()->locale)->toBe('en')
        ->and(session('locale'))->toBeNull();
});
