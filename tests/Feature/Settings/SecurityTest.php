<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

test('security page is displayed', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);
    Features::passkeys([
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManagePasskeys', true)
            ->where('passkeys', [])
            ->where('canManageTwoFactor', true)
            ->where('twoFactorEnabled', false),
        );
});

test('security page requires password confirmation when enabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    $user = User::factory()->create();

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('security.edit'));

    $response->assertRedirect(route('password.confirm'));
});

test('security page renders without two factor when feature is disabled', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    config(['fortify.features' => []]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Security')
            ->where('canManagePasskeys', false)
            ->where('passkeys', [])
            ->where('canManageTwoFactor', false)
            ->missing('twoFactorEnabled')
            ->missing('requiresConfirmation'),
        );
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

/**
 * **This is the only path that changes a password.** `profile.update` used to
 * accept `current_password` and `password` as well, and both flows are now
 * collapsed onto this one — see App\Concerns\ProfileValidationRules — so the
 * refusal below and the change above are the whole of that coverage rather than
 * one of two copies of it. The credential is asserted unchanged for the same
 * reason: a rejected proof that still wrote would be invisible from the status.
 */
test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrors('current_password')
        ->assertRedirect(route('security.edit'));

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

/**
 * The wiring proof for Actions\Profiles\UpdatePassword's reset-token delete.
 * The matrix of what that Action does lives in its own test; this asserts only
 * that the endpoint reaches it, because a controller that went back to writing
 * `$user->update([...])` itself would still change the password and still pass
 * every other test in this file while leaving the emailed reset link live for
 * the rest of `auth.passwords.users.expire`.
 */
test('changing the password kills an outstanding reset link', function () {
    $user = User::factory()->create();
    $token = Password::broker(config('fortify.passwords'))->createToken($user);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    expect(Password::broker(config('fortify.passwords'))->tokenExists($user, $token))->toBeFalse();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
});

test('redirects a guest to the login page and leaves every credential alone', function () {
    $user = User::factory()->create();

    $this->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('login'));

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

/**
 * `verified` sits on this route's group, so an account that never confirmed its
 * mailbox cannot rotate the credential that mailbox would be used to recover.
 */
test('redirects an unverified user to the verification notice and does not change the password', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect(route('verification.notice'));

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

/**
 * `throttle:6,1` is what stops a borrowed session being used to guess the
 * current password: the `current_password` rule is the only thing standing in
 * front of this endpoint, and an unthrottled one is an oracle.
 */
test('returns 429 on the seventh password change attempt in a minute', function () {
    $user = User::factory()->create();

    $payload = [
        'current_password' => 'wrong-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ];

    for ($attempt = 1; $attempt <= 6; $attempt++) {
        $this->actingAs($user)
            ->from(route('security.edit'))
            ->put(route('user-password.update'), $payload)
            ->assertRedirect(route('security.edit'));
    }

    $this->actingAs($user)
        ->put(route('user-password.update'), $payload)
        ->assertStatus(429);

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});
