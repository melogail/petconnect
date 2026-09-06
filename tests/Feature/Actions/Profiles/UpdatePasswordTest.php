<?php

use App\Actions\Profiles\UpdatePassword;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * The broker the Action resolves, resolved the same way it does — through
 * `config('fortify.passwords')` — so a test cannot pass against a broker the
 * Action never touches.
 */
function passwordBroker(): PasswordBroker
{
    /** @var PasswordBroker $broker */
    $broker = Password::broker(config('fortify.passwords'));

    return $broker;
}

/**
 * **The behaviour this Action was extracted to add.** Nothing in the codebase
 * called `deleteToken` before, so a user who asked for a reset link and then
 * changed the password from settings left the emailed link live for the whole
 * of `auth.passwords.users.expire` — a credential that outlives the one it
 * replaced, sitting in the mailbox that may be why the password was changed.
 *
 * `tokenExists()` is the question the reset flow actually asks, and the row is
 * asserted gone as well so a token that merely stopped validating (an expiry
 * bump, a hash change) cannot pass this.
 */
test('deletes an outstanding password reset token', function () {
    $user = User::factory()->create();
    $token = passwordBroker()->createToken($user);

    app(UpdatePassword::class)->handle($user, 'new-password');

    expect(passwordBroker()->tokenExists($user, $token))->toBeFalse();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
});

test('leaves another account reset token alone', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherToken = passwordBroker()->createToken($other);

    app(UpdatePassword::class)->handle($user, 'new-password');

    expect(passwordBroker()->tokenExists($other, $otherToken))->toBeTrue();
});

/**
 * `password` is cast `hashed`, so the Action hands the broker the plain-text
 * value and hashing here as well would double-hash it into a credential the
 * user could never sign in with. Asserting the column is not the plain string
 * is what separates "hashed once" from "not hashed at all".
 */
test('stores the new password hashed', function () {
    $user = User::factory()->create();

    app(UpdatePassword::class)->handle($user, 'new-password');

    $stored = $user->fresh()->password;

    expect(Hash::check('new-password', $stored))->toBeTrue()
        ->and($stored)->not->toBe('new-password');
});
