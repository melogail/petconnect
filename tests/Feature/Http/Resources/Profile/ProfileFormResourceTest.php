<?php

use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\Profile\ProfileFormResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Keys the resource emits that the form never posts back.
 *
 * `avatar` is the read name for the upload the write side calls `image`, and
 * the other two are display-only. Everything else has to have a rule of exactly
 * its own name.
 *
 * `created_at` was on this list and is off the payload: a join date is a fact
 * about the *public* person, which Http\Resources\Profile\ProfileResource
 * already carries for the page that shows it, and nothing read it here. The
 * exemption list is short on purpose — every entry is a key the parity test
 * below stops guarding.
 *
 * @var list<string>
 */
const PROFILE_FORM_READ_ONLY_KEYS = ['id', 'avatar', 'is_verified'];

/**
 * Rules for fields that are written but never read back.
 *
 * Just the avatar upload now. `current_password` and `password` were here while
 * this form accepted a password change; that moved wholesale to Fortify's
 * `user-password.update` (tests/Feature/Settings/SecurityTest), and leaving
 * them exempt would let the rules come back without the parity test noticing.
 *
 * @var list<string>
 */
const PROFILE_FORM_WRITE_ONLY_KEYS = ['image'];

/**
 * The rules the settings form actually validates against, asked of the real
 * Form Request rather than of the Concern, so the test sees what an HTTP
 * request would.
 *
 * @return list<string>
 */
function profileFormRuleKeys(User $user): array
{
    $request = UpdateProfileRequest::create(route('profile.update'), 'PATCH');
    $request->setUserResolver(fn (): User => $user);

    return array_values(array_diff(array_keys($request->rules()), PROFILE_FORM_WRITE_ONLY_KEYS));
}

/**
 * @return list<string>
 */
function profileFormResourceKeys(User $user): array
{
    $payload = ProfileFormResource::make($user->loadMissing('media'))->toArray(Request::create('/'));

    return array_values(array_diff(array_keys($payload), PROFILE_FORM_READ_ONLY_KEYS));
}

/**
 * **The only guard on this form.** `.ai/rules/requests.md` asks for `present`
 * alongside `nullable` on the scalar keys of a write bag, and that rule is
 * scoped to bags written *whole*: `pets.update` is a full replacement, so an
 * omitted key there must 422 rather than wipe the column.
 *
 * A profile save is the opposite. It is a PATCH — PersistProfileAttributes fills
 * only the keys the request sent, because the form is edited a section at a time
 * and full-replacement semantics would let a panel rendering half the fields
 * wipe the other half — so every optional key is `sometimes|nullable` and
 * `present` would 422 every partial save.
 *
 * The cost of that choice is that a key renamed in one file and not the other
 * **stops saving silently** instead of 422ing. Nothing else catches it: the
 * validator drops an unmatched key without complaint and the pipeline never sees
 * it. This test is the guard, and it fails from either side of the rename.
 */
test('emits exactly the keys the form request validates', function () {
    $user = User::factory()->create();

    expect(profileFormResourceKeys($user))
        ->toEqualCanonicalizing(profileFormRuleKeys($user));
});

/**
 * The read and write shapes of the avatar differ, so they have different names:
 * a client that posted back what it received would send a URL at a file rule and
 * 422. Same split as the pet form's `photos` (read) versus `images` (write).
 */
test('names the avatar read side avatar and the write side image', function () {
    $user = User::factory()->create();

    expect(profileFormRuleKeys($user))->not->toContain('avatar');
    expect(array_keys(ProfileFormResource::make($user)->toArray(Request::create('/'))))
        ->toContain('avatar')
        ->not->toContain('image');
});

/**
 * This payload is served from `profile.edit` alone, which sits behind `auth` and
 * acts on `$request->user()`. The *public* page's payload is ProfileResource and
 * carries none of these — the legacy resource emitted them on a route explicitly
 * marked public.
 */
test('carries the private fields the owner own form needs', function () {
    $user = User::factory()->create([
        'email' => 'nadia@example.com',
        'phone' => '+20-100-000-0000',
        'address' => '12 Nile Street',
    ]);

    expect(ProfileFormResource::make($user)->toArray(Request::create('/')))
        ->toMatchArray([
            'email' => 'nadia@example.com',
            'phone' => '+20-100-000-0000',
            'address' => '12 Nile Street',
        ]);
});

/**
 * The coordinates are emitted as stored rather than cast, so the value the form
 * posts back is the value that was saved. Asserted on the value and not on the
 * PHP type: the column is `decimal`, and whether the driver hands that back as a
 * string or a float is the driver's decision — PDO's MySQL driver says string
 * and SQLite says float, so a type assertion here would pass on CI and fail in
 * production or the reverse.
 */
test('round-trips the coordinates through the form without changing them', function () {
    $user = User::factory()->create(['lat' => 30.0444, 'lng' => 31.2357]);

    $payload = ProfileFormResource::make($user->fresh())->toArray(Request::create('/'));

    expect((float) $payload['lat'])->toBe(30.0444)
        ->and((float) $payload['lng'])->toBe(31.2357);
});

/**
 * `is_active` is on neither side: it is absent from User's #[Fillable] and from
 * the rules, because deactivation is a moderation decision on the `admins`
 * guard, not a checkbox on the owner's own form.
 */
test('exposes no deactivation control on either side of the form', function () {
    $user = User::factory()->create();

    expect(array_keys(ProfileFormResource::make($user)->toArray(Request::create('/'))))
        ->not->toContain('is_active');
    expect(profileFormRuleKeys($user))->not->toContain('is_active');
});
