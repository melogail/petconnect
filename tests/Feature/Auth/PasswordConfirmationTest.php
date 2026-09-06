<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('confirm password screen can be rendered', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('password.confirm'));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('auth/ConfirmPassword'),
    );
});

test('password confirmation requires authentication', function () {
    $response = $this->get(route('password.confirm'));

    $response->assertRedirect(route('login'));
});

/**
 * The other half of the gate. `tests/Feature/Settings/SecurityTest` proves the
 * gate closes — a visit to the security page without a confirmation redirects
 * here — but every test that needs it *open* forges the session key with
 * `withSession(['auth.password_confirmed_at' => time()])`, so nothing in the
 * suite has ever supplied a password and had the confirmation accepted.
 *
 * That leaves the whole key untested while the lock is well covered: a
 * ConfirmablePasswordController pointed at the wrong guard, or a broken
 * password check, would look exactly like a working suite and would either lock
 * every account out of its own security settings or let anyone holding a
 * hijacked session change them.
 */
test('opens the gate for the right password and sends the visitor on', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

    $this->actingAs($user)
        ->post(route('password.confirm.store'), ['password' => 'correct-horse'])
        ->assertSessionHasNoErrors();

    expect(session('auth.password_confirmed_at'))->not->toBeNull();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertOk();
});

test('leaves the gate shut for the wrong password', function () {
    $user = User::factory()->create(['password' => Hash::make('correct-horse')]);

    $this->actingAs($user)
        ->from(route('password.confirm'))
        ->post(route('password.confirm.store'), ['password' => 'battery-staple'])
        ->assertInvalid(['password']);

    expect(session('auth.password_confirmed_at'))->toBeNull();

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertRedirect(route('password.confirm'));
});
