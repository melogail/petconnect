<?php

use App\Actions\Profiles\UpdateProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Attach an avatar the way a previous save would have left one, so a test can
 * ask what happens to it when the next upload runs.
 *
 * `->image()` and never `->create()`: a faked `create()` writes zero bytes, the
 * conversion then early-returns before cleaning up, and every call leaks a
 * directory into `storage/media-library/temp/` (.ai/rules/tests.md).
 */
function attachAvatar(User $user, string $name = 'old-avatar.jpg'): Media
{
    return $user->addMedia(UploadedFile::fake()->image($name))
        ->toMediaCollection('users');
}

/**
 * Reject the next upload the way a full disk or a rejected write would, without
 * touching the filesystem: medialibrary refuses a file over
 * `media-library.max_file_size` from inside FileAdder, which is exactly where
 * UploadProfileImage fails.
 */
function rejectTheNextUpload(): void
{
    config(['media-library.max_file_size' => 1]);
}

describe('the avatar', function () {
    /**
     * **The ordering fix this flow exists for.** The legacy ProfileImageService
     * cleared the collection *before* uploading, so any failure in the upload
     * left the account with no avatar and nothing to restore. Here
     * UploadProfileImage runs first and throws out of the pipeline, so
     * ClearPreviousProfileImage is never reached.
     */
    test('keeps the existing avatar and its file when the upload fails', function () {
        Storage::fake(config('media-library.disk_name'));
        $user = User::factory()->create(['name' => 'Original Name']);
        $existing = attachAvatar($user);
        rejectTheNextUpload();

        expect(fn () => app(UpdateProfile::class)->handle(
            user: $user,
            attributes: ['name' => 'Renamed'],
            image: UploadedFile::fake()->image('new-avatar.jpg'),
        ))->toThrow(FileIsTooBig::class);

        $this->assertModelExists($existing);
        Storage::disk(config('media-library.disk_name'))
            ->assertExists($existing->getPathRelativeToRoot());
        expect($user->fresh()->load('media')->getMedia('users')->pluck('id')->all())
            ->toBe([$existing->getKey()])
            ->and($user->fresh()->name)->toBe('Original Name');
    });

    /**
     * The clear deletes exactly the ids snapshotted before the upload, never
     * `clearMediaCollection('users')` — which would take the file this run just
     * added with it.
     */
    test('replaces the avatar without deleting the file the run just uploaded', function () {
        Storage::fake(config('media-library.disk_name'));
        $user = User::factory()->create();
        $existing = attachAvatar($user);
        $existingPath = $existing->getPathRelativeToRoot();

        app(UpdateProfile::class)->handle(
            user: $user,
            attributes: [],
            image: UploadedFile::fake()->image('new-avatar.jpg'),
        );

        $replacement = $user->fresh()->load('media')->getMedia('users')->sole();
        $disk = Storage::disk(config('media-library.disk_name'));

        expect($replacement->getKey())->not->toBe($existing->getKey());
        $disk->assertExists($replacement->getPathRelativeToRoot());
        $this->assertModelMissing($existing);
        $disk->assertMissing($existingPath);
    });

    /**
     * The generated path is `media/{owner directory}/user/{id}/{media id}/`, and
     * MediaPathGenerator falls back to a database lookup per URL when the
     * `owner_directory` custom property is missing.
     */
    test('stamps the owner directory on the uploaded avatar', function () {
        Storage::fake(config('media-library.disk_name'));
        $user = User::factory()->create();

        app(UpdateProfile::class)->handle(
            user: $user,
            attributes: [],
            image: UploadedFile::fake()->image('avatar.jpg'),
        );

        expect($user->fresh()->load('media')->getMedia('users')->sole()->getCustomProperty('owner_directory'))
            ->toBe($user->media_directory_name);
    });

    test('attaches nothing when the save carries no file', function () {
        Storage::fake(config('media-library.disk_name'));
        $user = User::factory()->create();

        app(UpdateProfile::class)->handle($user, ['name' => 'Renamed']);

        $this->assertDatabaseEmpty('media');
        expect($user->fresh()->name)->toBe('Renamed');
    });
});

describe('the attribute bag', function () {
    /**
     * A profile save is a PATCH: PersistProfileAttributes fills only the keys
     * the request sent, so a form rendering one panel cannot wipe the others.
     */
    test('writes only the keys it was given and leaves the rest alone', function () {
        $user = User::factory()->create(['name' => 'Original', 'bio' => 'Fosters kittens.', 'city' => 'Cairo']);

        app(UpdateProfile::class)->handle($user, ['name' => 'Renamed']);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'name' => 'Renamed',
            'bio' => 'Fosters kittens.',
            'city' => 'Cairo',
        ]);
    });

    test('clears a field that was explicitly sent as null', function () {
        $user = User::factory()->create(['bio' => 'Fosters kittens.']);

        app(UpdateProfile::class)->handle($user, ['bio' => null]);

        expect($user->fresh()->bio)->toBeNull();
    });

    /**
     * Without this a user could reach a verified state on an address they do
     * not control: verify one email, then edit the field.
     */
    test('un-verifies the account when the email address changes', function () {
        $user = User::factory()->create();

        app(UpdateProfile::class)->handle($user, ['email' => 'moved@example.com']);

        expect($user->fresh()->email_verified_at)->toBeNull();
    });
});

/**
 * ApplyLocalePreference runs last and delegates to ApplyUserLocale, which is the
 * only writer of a locale in the application: application locale, session,
 * cookie and column, together.
 */
test('switches the language for the rest of the request, the session and the next visit', function () {
    $user = User::factory()->create(['locale' => 'en']);

    app(UpdateProfile::class)->handle($user, ['locale' => 'ar']);

    expect($user->fresh()->locale)->toBe('ar')
        ->and(app()->getLocale())->toBe('ar')
        ->and(session('locale'))->toBe('ar')
        ->and(Cookie::getQueuedCookies())->toHaveCount(1)
        ->and(Cookie::queued('locale')->getValue())->toBe('ar');
});

test('leaves the language alone when the save carries no locale', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    app(UpdateProfile::class)->handle($user, ['name' => 'Renamed']);

    expect($user->fresh()->locale)->toBe('ar')
        ->and(Cookie::getQueuedCookies())->toBe([]);
});
