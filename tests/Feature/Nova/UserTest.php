<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The back office's own field rules come from App\Concerns\ProfileValidationRules
 * — the same trait Http\Requests\Profile\UpdateProfileRequest and registration
 * use — rather than from a second list written next to the Nova fields.
 *
 * Before that, App\Nova\User carried rules for `name` and nothing else, so every
 * value below was written straight through: a `username` no URL could carry, a
 * `locale` User::preferredLocale() silently falls back from, a bio past the
 * `petconnect.profiles.bio_max_length` ceiling every read path assumes, and an
 * avatar that is not an image. A Select's `options()` never stopped any of it —
 * options are a rendering concern and the validator never sees them, which is
 * how `locale = klingon` reached the column.
 *
 * `locale` is spelled on every payload because App\Nova\User asks for
 * `localeRules(required: true)`: the control is a Select with no empty option,
 * so on this form the language is not optional the way it is on the member's.
 *
 * @return array<string, mixed>
 */
function novaUserPayload(User $user, array $overrides = []): array
{
    return [
        'name' => $user->name,
        'locale' => $user->locale,
        ...$overrides,
    ];
}

test('returns 422 to a member profile edited through Nova with an invalid value', function (array $overrides, string $field, string $message) {
    $admin = Admin::factory()->create();
    $member = User::factory()->create(['name' => 'Unchanged Name']);

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/users/{$member->getKey()}", novaUserPayload($member, [...$overrides, 'name' => 'Edited Name']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => $message]);

    expect($member->fresh()->name)->toBe('Unchanged Name');
})->with([
    'a username no URL could carry' => [
        ['username' => 'not a valid handle!!!'],
        'username',
        'The Username field must only contain letters, numbers, dashes, and underscores.',
    ],
    'a language the application does not support' => [
        ['locale' => 'klingon'],
        'locale',
        'The selected Locale is invalid.',
    ],
]);

/**
 * Spelled out rather than folded into the dataset above, because the ceiling is
 * `petconnect.profiles.bio_max_length` and a literal 1001-character string in a
 * dataset would read as a magic number that happens to work.
 */
test('returns 422 to a bio one character past the configured ceiling', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->create();
    $ceiling = config('petconnect.profiles.bio_max_length');

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/users/{$member->getKey()}", novaUserPayload($member, [
            'bio' => str_repeat('a', $ceiling + 1),
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('bio');

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/users/{$member->getKey()}", novaUserPayload($member, [
            'bio' => str_repeat('a', $ceiling),
        ]))
        ->assertOk();

    expect(strlen($member->fresh()->bio))->toBe($ceiling);
});

/**
 * The Images field applies ProfileValidationRules::avatarFileRules() per file
 * through `singleMediaRules()`, so the admin path and the member path cannot
 * disagree about what an avatar is. Sent as a real request body rather than
 * JSON because the field only validates values that arrive as uploaded files.
 */
test('returns 422 to an avatar that is not an image', function () {
    Storage::fake(config('media-library.disk_name'));
    $admin = Admin::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($admin, 'admin')
        ->put("/nova-api/users/{$member->getKey()}", novaUserPayload($member, [
            '__media__' => ['users' => [UploadedFile::fake()->createWithContent('notes.txt', 'not an image')]],
        ]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['users' => 'The users field must be an image.']);

    expect($member->fresh()->getMedia('users'))->toHaveCount(0);
});

test('accepts an avatar that is an image', function () {
    Storage::fake(config('media-library.disk_name'));
    $admin = Admin::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($admin, 'admin')
        ->put("/nova-api/users/{$member->getKey()}", novaUserPayload($member, [
            '__media__' => ['users' => [UploadedFile::fake()->image('avatar.jpg')]],
        ]), ['Accept' => 'application/json'])
        ->assertOk();

    expect($member->fresh()->getMedia('users'))->toHaveCount(1);
});

test('accepts a handle, a language and a bio the application supports', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/users/{$member->getKey()}", novaUserPayload($member, [
            'username' => 'a-valid_handle1',
            'locale' => 'ar',
            'bio' => 'Fosters kittens.',
        ]))
        ->assertOk();

    expect($member->fresh())
        ->username->toBe('a-valid_handle1')
        ->locale->toBe('ar')
        ->bio->toBe('Fosters kittens.');
});

/**
 * `lat` and `lng` are read as one value everywhere — Pet::nearby() cannot use
 * half a coordinate — and ProfileValidationRules carries `required_with` in
 * both directions to stop half of one being written.
 *
 * It used to be unreachable. The pair carried `sometimes`, which gates the
 * **entire attribute** rather than the rules after it: Validator::
 * passesOptionalCheck() returns false the moment the key is absent from the
 * payload, so with `lng` omitted every rule on it was skipped, `required_with:
 * lat` included, and the pair could only ever fire when both keys were already
 * present — the one case it is not needed for. Measured then, this exact
 * request returned 200 and wrote `lat = 51.5, lng = null`.
 *
 * It was never a Nova defect and the fix was not a Nova fix: `sometimes` is off
 * the pair in App\Concerns\ProfileValidationRules and `nullable` carries the
 * optionality instead. The member form was writing the same half coordinate on
 * a 302 and is pinned the same way in tests/Feature/Settings/ProfileUpdateTest,
 * because one rule change has to hold at both entry points.
 */
test('returns 422 to a latitude with no longitude', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->create(['lat' => null, 'lng' => null]);

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/users/{$member->getKey()}", novaUserPayload($member, ['lat' => 51.5]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lng' => 'The Longitude field is required when Latitude is present.']);

    expect($member->fresh())
        ->lat->toBeNull()
        ->lng->toBeNull();
});

/**
 * The optionality `sometimes` used to carry is carried by `nullable`, so an
 * edit that mentions neither coordinate is still a clean save rather than a
 * pair of required-field errors.
 */
test('saves a member edited through Nova with neither coordinate on the payload', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->create(['lat' => null, 'lng' => null, 'name' => 'Original Name']);

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/users/{$member->getKey()}", novaUserPayload($member, ['name' => 'Edited Name']))
        ->assertOk();

    expect($member->fresh())
        ->name->toBe('Edited Name')
        ->lat->toBeNull();
});

/**
 * The other half of the pair: with both keys on the payload the range checks
 * run, which is what `required_with` was always able to reach.
 */
test('returns 422 to a longitude outside the range', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->create(['lat' => null, 'lng' => null]);

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/users/{$member->getKey()}", novaUserPayload($member, ['lat' => 51.5, 'lng' => 500]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lng');

    expect($member->fresh()->lat)->toBeNull();
});
