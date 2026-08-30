<?php

use App\MediaLibrary\MediaPathGenerator;
use App\Models\Category;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Persist a media row the way an upload would, without touching the image
 * pipeline: this machine has neither gd nor imagick.
 *
 * @param  array<string, mixed>  $customProperties
 */
function mediaRowFor(string $modelType, int $modelId, array $customProperties = []): Media
{
    $media = new Media;

    $media->forceFill([
        'model_type' => $modelType,
        'model_id' => $modelId,
        'collection_name' => 'pets',
        'name' => 'photo',
        'file_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'disk' => 'public',
        'size' => 1024,
        'manipulations' => [],
        'custom_properties' => $customProperties,
        'generated_conversions' => [],
        'responsive_images' => [],
    ])->save();

    return $media;
}

test('stores a pet photo under the owner directory, the morph alias and the media id', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $media = mediaRowFor('pet', $pet->getKey(), ['owner_directory' => $owner->media_directory_name]);

    $path = app(MediaPathGenerator::class)->getPath($media);

    expect($path)->toBe("media/{$owner->media_directory_name}/pet/{$pet->getKey()}/{$media->getKey()}/");
});

test('stores pet conversions and responsive images beneath the media path', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $media = mediaRowFor('pet', $pet->getKey(), ['owner_directory' => $owner->media_directory_name]);

    $generator = app(MediaPathGenerator::class);

    $base = "media/{$owner->media_directory_name}/pet/{$pet->getKey()}/{$media->getKey()}/";

    expect($generator->getPathForConversions($media))->toBe($base.'conversions/')
        ->and($generator->getPathForResponsiveImages($media))->toBe($base.'responsive-images/');
});

test('stores an avatar under the user directory and the user morph alias', function () {
    $user = User::factory()->create();
    $media = mediaRowFor('user', $user->getKey(), ['owner_directory' => $user->media_directory_name]);

    $generator = app(MediaPathGenerator::class);

    $base = "media/{$user->media_directory_name}/user/{$user->getKey()}/{$media->getKey()}/";

    expect($generator->getPath($media))->toBe($base)
        ->and($generator->getPathForConversions($media))->toBe($base.'conversions/')
        ->and($generator->getPathForResponsiveImages($media))->toBe($base.'responsive-images/');
});

test('builds the path without querying for the owning model', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $stored = mediaRowFor('pet', $pet->getKey(), ['owner_directory' => $owner->media_directory_name]);
    $media = Media::query()->findOrFail($stored->getKey());

    DB::enableQueryLog();
    app(MediaPathGenerator::class)->getPath($media);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty()
        ->and($media->relationLoaded('model'))->toBeFalse();
});

test('falls back to the listing owner when the media row carries no owner directory', function () {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $media = mediaRowFor('pet', $pet->getKey());

    $path = app(MediaPathGenerator::class)->getPath($media);

    expect($path)->toBe("media/{$owner->media_directory_name}/pet/{$pet->getKey()}/{$media->getKey()}/");
});

test('omits the owner segment for a model that belongs to no user', function () {
    $category = Category::factory()->create();
    $media = mediaRowFor('category', $category->getKey());

    $path = app(MediaPathGenerator::class)->getPath($media);

    expect($path)->toBe("media/category/{$category->getKey()}/{$media->getKey()}/");
});
