<?php

use App\Models\Review;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

/**
 * `username` became editable in this phase: nullable, `alpha_dash`, 3-50, unique
 * with the current row ignored. It is a public handle and nothing more —
 * User::getRouteKeyName() stays `id`, which
 * tests/Feature/Http/Controllers/Web/ProfileControllerTest pins.
 */
test('the public handle can be changed', function () {
    $user = User::factory()->create(['username' => 'old-handle']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'username' => 'nadia-aziz',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->username)->toBe('nadia-aziz');
});

test('the public handle can be cleared, because the column is nullable', function () {
    $user = User::factory()->create(['username' => 'old-handle']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'username' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->username)->toBeNull();
});

test('a save that omits the handle leaves it alone, because the form is a patch', function () {
    $user = User::factory()->create(['username' => 'old-handle']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Renamed', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($user->fresh())
        ->username->toBe('old-handle')
        ->name->toBe('Renamed');
});

test('a handle already taken by somebody else is rejected', function () {
    User::factory()->create(['username' => 'taken']);
    $user = User::factory()->create(['username' => 'mine']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'username' => 'taken',
        ])
        ->assertInvalid(['username' => 'The username has already been taken.']);

    expect($user->fresh()->username)->toBe('mine');
});

test('keeping your own handle is not a uniqueness conflict', function () {
    $user = User::factory()->create(['username' => 'mine']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Renamed',
            'email' => $user->email,
            'username' => 'mine',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Renamed');
});

test('a handle that is not url safe is rejected', function (string $username, string $message) {
    $user = User::factory()->create(['username' => 'mine']);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'username' => $username,
        ])
        ->assertInvalid(['username' => $message]);

    expect($user->fresh()->username)->toBe('mine');
})->with([
    'spaces and punctuation' => ['nadia aziz!', 'The username field must only contain letters, numbers, dashes, and underscores.'],
    'shorter than three characters' => ['ab', 'The username field must be at least 3 characters.'],
    'longer than fifty characters' => [str_repeat('a', 51), 'The username field must not be greater than 50 characters.'],
]);

/**
 * The form is multipart because it carries a file, and the upload key is
 * `image` — the read side calls it `avatar` and emits a URL, so a client that
 * posted back what it received would send a URL at a file rule.
 */
test('the avatar is uploaded through the settings form', function () {
    Storage::fake(config('media-library.disk_name'));
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'image' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $avatar = $user->fresh()->load('media')->getMedia('users')->sole();

    Storage::disk(config('media-library.disk_name'))->assertExists($avatar->getPathRelativeToRoot());
});

test('a file that is not an image is rejected and nothing is attached', function () {
    Storage::fake(config('media-library.disk_name'));
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'image' => UploadedFile::fake()->createWithContent('notes.pdf', 'not an image'),
        ])
        ->assertInvalid(['image']);

    $this->assertDatabaseEmpty('media');
});

test('an avatar over the configured ceiling is rejected and nothing is attached', function () {
    Storage::fake(config('media-library.disk_name'));
    config(['petconnect.profiles.max_avatar_kilobytes' => 10]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'image' => UploadedFile::fake()->image('huge.jpg', 2000, 2000),
        ])
        ->assertInvalid(['image' => 'must not be greater than 10 kilobytes']);

    $this->assertDatabaseEmpty('media');
});

/**
 * **This form no longer changes a password, deliberately.** It used to carry
 * `current_password` and `password` rules and two pipeline steps behind them,
 * while `settings/Security` changed the same credential through Fortify's
 * `user-password.update` — two endpoints and two error vocabularies for one
 * outcome. `user-password.update` is now the single path (see
 * App\Concerns\ProfileValidationRules), and the wrong-proof and successful
 * cases live in tests/Feature/Settings/SecurityTest.
 *
 * That is a divergence from the legacy app rather than a port of it, so the
 * refusal is pinned rather than left implicit. It is a *silent* refusal — the
 * validator drops a key it has no rule for and the pipeline never sees it — so
 * a save carrying the pair succeeds and changes nothing, which is precisely the
 * outcome a client would otherwise be told was a password change.
 */
test('ignores a password sent to the profile form and leaves the credential alone', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Renamed',
            'email' => $user->email,
            'current_password' => 'password',
            'password' => 'a-new-secret-password',
            'password_confirmation' => 'a-new-secret-password',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh())->name->toBe('Renamed')
        ->and(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

/**
 * `lat` and `lng` are read as one value everywhere — Pet::nearby() cannot use
 * half a coordinate — so `required_with` pairs them in both directions.
 *
 * It did not work, and this is the member-side half of the fix. The pair used
 * to carry `sometimes`, which gates the **entire attribute**:
 * Validator::passesOptionalCheck() returns false the moment the key is absent,
 * skipping every rule on it, `required_with` included. Measured before the fix,
 * this exact request returned a 302 and wrote `lat = 51.5, lng = null`; the
 * same payload through App\Nova\User wrote the same half coordinate on a 200,
 * which is why both entry points are pinned (tests/Feature/Nova/UserTest).
 */
test('refuses a latitude with no longitude', function () {
    $user = User::factory()->create(['lat' => null, 'lng' => null]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'lat' => 51.5,
        ])
        ->assertInvalid(['lng' => 'The lng field is required when lat is present.']);

    expect($user->fresh())->lat->toBeNull()->lng->toBeNull();
});

test('refuses a longitude with no latitude', function () {
    $user = User::factory()->create(['lat' => null, 'lng' => null]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'lng' => -0.12,
        ])
        ->assertInvalid(['lat' => 'The lat field is required when lng is present.']);

    expect($user->fresh())->lat->toBeNull()->lng->toBeNull();
});

/**
 * The optionality `sometimes` used to carry is carried by `nullable` instead,
 * so a save that mentions neither coordinate is still a clean PATCH rather than
 * a pair of required-field errors.
 */
test('saves a profile that mentions neither coordinate', function () {
    $user = User::factory()->create(['lat' => 30.0444, 'lng' => 31.2357]);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Renamed', 'email' => $user->email])
        ->assertSessionHasNoErrors();

    expect($user->fresh())
        ->name->toBe('Renamed')
        ->and((float) $user->fresh()->lat)->toBe(30.0444);
});

test('accepts a whole coordinate', function () {
    $user = User::factory()->create(['lat' => null, 'lng' => null]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'lat' => 30.0444,
            'lng' => 31.2357,
        ])
        ->assertSessionHasNoErrors();

    expect((float) $user->fresh()->lat)->toBe(30.0444)
        ->and((float) $user->fresh()->lng)->toBe(31.2357);
});

/**
 * The controller delegates to Actions\Profiles\DeleteUserAccount rather than
 * calling `$user->delete()`, which is what stops the cascade stranding every
 * polymorphic row that reaches its target through a morph column. The whole
 * cleanup is asserted in tests/Feature/Actions/Profiles/DeleteUserAccountTest;
 * this is the one case that proves the endpoint runs it.
 */
test('deleting the account clears the reviews written about it', function () {
    $user = User::factory()->create();
    $reviewAboutThem = Review::factory()->forUser($user)->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertModelMissing($reviewAboutThem);
});

/**
 * Nothing of the session may survive the row it belonged to, so the delete
 * invalidates it and regenerates the CSRF token rather than merely logging out.
 */
test('deleting the account invalidates the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => 'token-from-before', 'checkout.step' => 'payment'])
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionMissing('checkout.step');

    expect(session('_token'))->not->toBe('token-from-before');
});
