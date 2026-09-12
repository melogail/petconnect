<?php

use App\Models\Admin;
use App\Models\User;
use App\Providers\HorizonServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Horizon is back-office infrastructure, so it is gated on the same account
 * type Nova is: an App\Models\Admin on the `admin` guard.
 *
 * The trap these tests exist to pin is that the gate cannot be written the way
 * `viewNova` is. `horizon.middleware` is `['web']` and nothing in that stack
 * calls `shouldUse('admin')`, so the user Horizon hands the ability —
 * `Gate::check('viewHorizon', [$request->user()])` — is resolved from the
 * **default** guard and is null for an admin. A `$user instanceof Admin` body
 * would therefore refuse every admin and admit nobody at all, which is a
 * green-looking failure: "nobody can reach Horizon" and "only admins can reach
 * Horizon" are indistinguishable from a suite that only checks the refusals.
 *
 * @see HorizonServiceProvider::gate()
 */

/**
 * A Nova session as a browser actually presents one: signed in on `admin`,
 * with the default guard left alone.
 *
 * **`actingAs($admin, 'admin')` must not be used here, and that is measured.**
 * `InteractsWithAuthentication::be()` calls `shouldUse($guard)` as well as
 * `setUser()`, which makes `$request->user()` return the Admin and quietly
 * repairs the exact bug under test — with `actingAs()` the broken
 * `$user instanceof Admin` gate passes all of these. `setUser()` alone keeps
 * the default guard on `web`, so the request arrives the way a real one does.
 */
function signInOnTheAdminGuardOnly(): Admin
{
    $admin = Admin::factory()->create();

    Auth::guard('admin')->setUser($admin);

    expect(Auth::getDefaultDriver())->toBe('web')
        ->and(Auth::user())->toBeNull();

    return $admin;
}

test('lets an admin signed in on the admin guard reach the horizon dashboard', function () {
    signInOnTheAdminGuardOnly();

    $this->get('/horizon')->assertOk();
});

test('forbids a member on the web guard from the horizon dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/horizon')
        ->assertForbidden();
});

test('forbids a guest from the horizon dashboard', function () {
    $this->get('/horizon')->assertForbidden();
});

/**
 * The ids colliding is the shape that would go unnoticed if the gate ever read
 * the passed user instead of the guard.
 */
test('does not treat a member as the admin sharing their id', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->create();

    expect($member->getKey())->toBe($admin->getKey());

    $this->actingAs($member)->get('/horizon')->assertForbidden();
});

/**
 * `Gate::allows()` rather than `Gate::forUser()`: the gate answers from the
 * `admin` guard's session, not from the user it is handed, so passing a subject
 * in would assert nothing about it. With no user on the default guard the
 * ability is resolved as a **guest** check, and Laravel's Gate refuses to offer
 * an ability to a guest whose first parameter does not accept null — so this
 * also pins `mixed $user = null`, which is what keeps a guest a plain false
 * rather than a TypeError and a 500.
 */
test('grants the viewHorizon gate to an admin the default guard cannot see', function () {
    signInOnTheAdminGuardOnly();

    expect(Gate::allows('viewHorizon'))->toBeTrue();
});

test('refuses the viewHorizon gate to a member', function () {
    $this->actingAs(User::factory()->create());

    expect(Gate::allows('viewHorizon'))->toBeFalse();
});

test('refuses the viewHorizon gate to a guest', function () {
    expect(Gate::allows('viewHorizon'))->toBeFalse()
        ->and(Gate::forUser(null)->allows('viewHorizon'))->toBeFalse();
});

/**
 * The local-environment bypass is Horizon's, not ours:
 * `HorizonApplicationServiceProvider::authorization()` registers
 * `Gate::check(...) || app()->environment('local')`. The suite runs as
 * `testing` (phpunit.xml), which is why the refusals above are observable at
 * all — on a developer's `local` box Horizon is open to everyone, guests
 * included, and that is deliberately left alone.
 */
test('relies on the gate rather than the environment bypass in this suite', function () {
    expect(app()->environment('local'))->toBeFalse();
});
