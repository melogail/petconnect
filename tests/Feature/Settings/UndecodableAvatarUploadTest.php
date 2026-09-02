<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * An avatar that every check short of a full decode accepts.
 *
 * A genuine JPEG SOI marker followed by a JFIF APP0 header and `0x41` padding:
 * `finfo` sniffs it as `image/jpeg`, so it clears
 * `ProfileValidationRules::avatarFileRules()` (`image`,
 * `ConvertibleImageTypes::mimesRule()`, `max:`) — and GD then fails on it the
 * moment a conversion asks for the pixels, raising CouldNotLoadImage.
 * Measured, not assumed: 3020 bytes, `finfo` says `image/jpeg`,
 * `Image::useImageDriver('gd')->loadFile()` throws.
 *
 * The construction is the one `tests/Feature/MediaLibrary/UndecodableImageUploadTest`
 * already uses for the listing form; it is restated under its own name because
 * a Pest helper is a plain global function and two files declaring the same one
 * is a fatal redeclaration when both are loaded in one run.
 *
 * Never `UploadedFile::fake()->image()` — that is decodable and defeats the
 * test — and never `->create()`, which writes zero bytes and takes an entirely
 * different early return inside the conversion.
 */
function undecodableAvatarUpload(string $name = 'corrupt-avatar.jpg'): UploadedFile
{
    $bytes = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
        .str_repeat("\x41", 3000);

    return UploadedFile::fake()->createWithContent($name, $bytes);
}

/**
 * Attach an avatar the way a previous save would have left one, so a test can
 * ask what the next upload does to it.
 */
function attachExistingAvatar(User $user, string $name = 'old-avatar.jpg'): Media
{
    return $user->addMedia(UploadedFile::fake()->image($name))
        ->toMediaCollection('users');
}

/**
 * The settings form's identity pair, which is `required` on every save.
 *
 * @return array<string, mixed>
 */
function avatarSavePayload(User $user): array
{
    return [
        'name' => $user->name,
        'email' => $user->email,
        'image' => undecodableAvatarUpload(),
    ];
}

/**
 * **The claim under test, and the reason this file exists.**
 *
 * A routine profile edit must never cost the user the avatar they already had.
 * Whatever status the failed replacement produces, the previous media row and
 * its bytes have to be exactly where they were — a 422 that has already deleted
 * the old avatar is still data loss, with a friendlier status code.
 *
 * Two distinct mechanisms protect it, covering different failures — do not read
 * either as doing the other's job, and note that only the first is in play here:
 *
 * - **Ordering** covers a bad *new* image, which is this test.
 *   `EnsureProfileImageIsDecodable` and `UploadProfileImage` both run before
 *   `Pipelines\Profiles\UpdateProfile\ClearPreviousProfileImage`, so the decode
 *   failure never reaches the clear step: `uploadedMedia()` is still null, the
 *   step's guard short circuits, and no delete is ever registered. The previous
 *   row and its bytes are untouched by construction rather than restored by a
 *   rollback, and `DB::afterCommit()` plays no part — there is no callback.
 * - **`DB::afterCommit()`** covers a *good* new image and a failure later in the
 *   run (PersistProfileAttributes, ApplyLocalePreference, a unique race on
 *   `username` or `email`). The clear step defers its delete there, so the
 *   callback is discarded with the transaction and the old file survives that
 *   case too.
 *
 * This asserts the outcome, not either mechanism.
 */
test('keeps the existing avatar and its file when the replacement cannot be decoded', function () {
    Storage::fake(config('media-library.disk_name'));
    $user = User::factory()->create();
    $existing = attachExistingAvatar($user);
    $existingPath = $existing->getPathRelativeToRoot();

    $this->actingAs($user)->patch(route('profile.update'), avatarSavePayload($user));

    $this->assertModelExists($existing);
    Storage::disk(config('media-library.disk_name'))->assertExists($existingPath);
    expect($user->fresh()->load('media')->getMedia('users')->pluck('id')->all())
        ->toBe([$existing->getKey()]);
});

/**
 * A file the conversion driver cannot read is a field-level input problem: the
 * user picked the wrong file and can pick another one. It has to be refused at
 * the validator, keyed on `image` — the write name for the avatar, `avatar`
 * being the read name on ProfileFormResource (.ai/rules/profile.md).
 *
 * A 500 here is the defect: it tells the user nothing they can act on, and it
 * is what the decode failure produced while nothing in this flow verified the
 * upload up front. `Pipelines\Profiles\UpdateProfile\EnsureProfileImageIsDecodable`
 * is the step that closed it, running the same
 * `MediaLibrary\ImageDecodeVerifier` the listing flow's
 * `Pipelines\Pets\Shared\EnsurePhotosAreDecodable` uses, ahead of
 * `UploadProfileImage`.
 */
test('refuses an undecodable avatar with a 422 on the image field', function () {
    Storage::fake(config('media-library.disk_name'));
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), avatarSavePayload($user))
        ->assertInvalid(['image']);
});

/**
 * Separate from the survival assertion above on purpose: the old avatar
 * surviving says nothing about what the failed run left behind. A committed
 * media row whose conversion was never written is a broken image with no file —
 * `Media::getUrl('display')` does not fall back to the original.
 *
 * Both halves are asserted because only one of them is new. The **row** was
 * always discarded by the Action's transaction, so `assertDatabaseEmpty` pins
 * behaviour that predates the decode check. The **file** is the half no
 * rollback can reclaim, and it is the leak the check actually cures: verified
 * in vendor source, `FileAdder::processMediaItem()` writes the row, then
 * `Filesystem::add()` runs `copyToMediaLibrary()` and only afterwards
 * `createDerivedFiles()` — so the original's bytes were already on the disk by
 * the time the conversion threw. The empty-disk assertion is therefore the one
 * that fails against a flow which uploads first and discovers the bad decode
 * afterwards. The fixture attaches no avatar of its own, so "no files at all"
 * is exact rather than a before/after difference.
 */
test('leaves no media row and no file behind for an avatar that failed to convert', function () {
    Storage::fake(config('media-library.disk_name'));
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), avatarSavePayload($user));

    $this->assertDatabaseEmpty('media');
    expect(Storage::disk(config('media-library.disk_name'))->allFiles())->toBeEmpty();
});
