<?php

use App\Models\User;
use Illuminate\Support\Facades\App;

/**
 * The picker is in the header of every page, including the ones a guest can
 * read, so a guest's choice has to stick — and the only place it can stick is
 * the cookie, since there is no row to write to.
 */
test('lets a guest switch language and remembers it in a cookie', function () {
    $this->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'ar'])
        ->assertRedirect(route('home'))
        ->assertSessionHasNoErrors()
        ->assertPlainCookie('locale', 'ar');

    expect(session('locale'))->toBe('ar');
});

/**
 * `users.locale` is what User::preferredLocale() reads, which is what queued
 * mail and notifications are rendered in — a user who switched to Arabic in the
 * browser and then gets an English verification email has not really switched.
 */
test('writes the language onto a signed in account as well', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'ar'])
        ->assertRedirect(route('home'))
        ->assertPlainCookie('locale', 'ar');

    expect($user->fresh()->locale)->toBe('ar');
});

/**
 * The response is rendered *after* ApplyUserLocale has called App::setLocale(),
 * so the redirect's own flash message is already in the new language.
 */
test('switches the language for the rest of the request', function () {
    App::setLocale('en');

    $this->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'ar'])
        ->assertRedirect(route('home'));

    expect(App::getLocale())->toBe('ar');
});

test('rejects a language that is not on the whitelist and changes nothing', function () {
    $user = User::factory()->create(['locale' => 'en']);

    $this->actingAs($user)
        ->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'de'])
        ->assertInvalid(['locale'])
        ->assertCookieMissing('locale');

    expect($user->fresh()->locale)->toBe('en');
});

/**
 * Required rather than optional, unlike the profile form's `locale`: this
 * request exists only to change the language, so an empty submission means
 * nothing.
 */
test('rejects a submission with no language at all', function () {
    $this->from(route('home'))
        ->post(route('locale.update'), [])
        ->assertInvalid(['locale' => 'The locale field is required.']);
});

/**
 * A GET that writes is a bug under Inertia v3 rather than a purity argument:
 * prefetching issues real GET requests on hover, so a `GET /locale/ar` link
 * would switch the whole site to Arabic as the pointer crossed the menu.
 */
test('refuses a GET, because switching language is a write', function () {
    $this->get('/locale')->assertMethodNotAllowed();
});
