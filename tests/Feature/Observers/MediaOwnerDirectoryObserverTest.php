<?php

use App\MediaLibrary\MediaPathGenerator;
use App\MediaLibrary\OwnerDirectoryResolver;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
});

/**
 * The property is what keeps MediaPathGenerator from having to look the owner
 * up, and the two application upload paths set it themselves. Every *other*
 * writer had to remember to, and none of them did: the Nova media field fills
 * `withCustomProperties($this->customProperties)` with whatever the field was
 * configured with, which is empty for all four of ours.
 *
 * The stamp is asserted on the stored row rather than on the instance, because
 * `creating` is chosen precisely so the value is in the row the path is
 * generated from with no second UPDATE.
 */
test('stamps the owner directory on a listing photo attached without one', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $photo = $pet->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection(Pet::PHOTO_COLLECTION);

    expect(Media::query()->findOrFail($photo->getKey())->getCustomProperty(MediaPathGenerator::OWNER_DIRECTORY_PROPERTY))
        ->toBe($owner->media_directory_name);
});

/**
 * The point of stamping at all. Without the property the generator falls back
 * to OwnerDirectoryResolver's database lookup — on the public listing page, for
 * every card — and its own docblock says that fallback is not to be relied on.
 *
 * The row is re-read from the database and the resolver from a fresh container
 * so neither the in-memory model nor the resolver's per-request memo can answer
 * the question the stamp is supposed to answer.
 */
test('builds the stored path from the stamped directory without a fallback query', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $photo = $pet->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection(Pet::PHOTO_COLLECTION);

    $stored = Media::query()->findOrFail($photo->getKey());
    app()->forgetInstance(OwnerDirectoryResolver::class);
    app()->forgetInstance(MediaPathGenerator::class);
    $generator = app(MediaPathGenerator::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $path = $generator->getPath($stored);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($path)->toBe("media/{$owner->media_directory_name}/pet/{$pet->getKey()}/{$stored->getKey()}/")
        ->and($queries)->toBeEmpty();
});

/**
 * The path that was measured missing it: an avatar replaced from the back
 * office. Ebess\AdvancedNovaMediaLibrary\Fields\Media::addNewMedia sets only the
 * field's own configured custom properties, so before the observer every file
 * an admin uploaded was permanently unstamped.
 */
test('stamps the owner directory on an avatar uploaded through Nova', function () {
    $admin = Admin::factory()->create();
    $member = User::factory()->create();

    $this->actingAs($admin, 'admin')
        ->put("/nova-api/users/{$member->getKey()}", [
            'name' => $member->name,
            'locale' => $member->locale,
            '__media__' => ['users' => [UploadedFile::fake()->image('avatar.jpg')]],
        ])
        ->assertOk();

    $avatar = $member->fresh()->getFirstMedia('users');

    expect($avatar->getCustomProperty(MediaPathGenerator::OWNER_DIRECTORY_PROPERTY))
        ->toBe($member->media_directory_name)
        ->and($avatar->getPathRelativeToRoot())
        ->toStartWith("media/{$member->media_directory_name}/user/{$member->getKey()}/");
});

/**
 * The uploader that already supplies the directory — UpdateProfile's
 * UploadProfileImage and Actions\Pets\ResolveMediaOwnerDirectory — keeps what it
 * sent. The hook fills a gap; it does not adjudicate.
 */
test('keeps an owner directory the uploader supplied', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $photo = $pet->addMedia(UploadedFile::fake()->image('cover.jpg'))
        ->withCustomProperties([MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => 'supplied-by-the-uploader'])
        ->toMediaCollection(Pet::PHOTO_COLLECTION);

    expect(Media::query()->findOrFail($photo->getKey())->getCustomProperty(MediaPathGenerator::OWNER_DIRECTORY_PROPERTY))
        ->toBe('supplied-by-the-uploader');
});

/**
 * A global model has no owner, so there is nothing to stamp and the path has no
 * owner segment — the same conclusion MediaPathGenerator reaches on its own.
 */
test('stamps nothing on a category icon, which belongs to no member', function () {
    $category = Category::factory()->create();

    $icon = $category->addMedia(UploadedFile::fake()->image('icon.jpg'))->toMediaCollection('categories');

    expect(Media::query()->findOrFail($icon->getKey())->getCustomProperty(MediaPathGenerator::OWNER_DIRECTORY_PROPERTY))
        ->toBeNull()
        ->and($icon->getPathRelativeToRoot())
        ->toStartWith("media/category/{$category->getKey()}/");
});
