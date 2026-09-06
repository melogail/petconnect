<?php

use App\Models\Category;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Exceptions\CouldNotLoadImage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * An upload that every check short of a full decode accepts.
 *
 * A genuine JPEG SOI marker followed by a JFIF APP0 header and padding: finfo
 * sniffs it as `image/jpeg`, so it clears `PetPhotoRules::photoFileRules()`
 * (`image`, `mimes:jpeg,...`, `max:`) — and then GD fails on it the moment a
 * conversion asks for the pixels, which raises CouldNotLoadImage from inside
 * `FileManipulator::performConversions()`.
 *
 * It must never be built with `UploadedFile::fake()->image()` (decodable, so no
 * throw) or `->create()` (0 bytes, which takes the other early return).
 */
function undecodableJpegUpload(string $name = 'corrupt.jpg'): UploadedFile
{
    $bytes = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
        .str_repeat("\x41", 3000);

    return UploadedFile::fake()->createWithContent($name, $bytes);
}

/**
 * How many temporary conversion directories medialibrary is currently holding.
 *
 * Counting entries, not measuring bytes: ext4 never gives directory blocks
 * back, so `du` keeps reporting the high-water mark of a directory that has
 * already been emptied. The path is a raw `storage_path()`, not a disk, so
 * `Storage::fake()` never redirects it.
 */
function mediaTemporaryDirectoryCount(): int
{
    $path = config('media-library.temporary_directory_path') ?? storage_path('media-library/temp');

    return count(glob(rtrim($path, '/').'/*', GLOB_ONLYDIR) ?: []);
}

/**
 * A complete listing payload whose cover photo cannot be decoded.
 *
 * Restated here rather than shared with PetControllerTest because a Pest helper
 * only exists while the file that declares it is loaded, and these two tests are
 * run on their own.
 *
 * @return array<string, mixed>
 */
function undecodablePetPayload(Category $category): array
{
    return [
        'name' => 'Luna',
        'category_id' => $category->getKey(),
        'breed_id' => null,
        'age' => '2',
        'gender' => 'female',
        'color' => 'Black',
        'weight' => '4.2',
        'description' => 'A calm indoor cat looking for a quiet home.',
        'listing_type' => 'adoption',
        'price' => null,
        'status' => 'available',
        'location' => [
            'address' => '12 Nile Street',
            'detailedAddress' => 'Building 3, Apartment 7',
            'city' => 'Cairo',
            'state' => 'Cairo',
            'postalCode' => '11511',
            'country' => 'Egypt',
            'coordinates' => ['lat' => '30.0444', 'lng' => '31.2357'],
        ],
        'health' => [
            'status' => 'healthy',
            'vaccinated' => true,
            'spayedNeutered' => true,
            'specialNeeds' => 'None',
            'lastVetVisit' => '2024-01-15',
            'vaccinations' => [['name' => 'Rabies', 'date' => '2024-01-15']],
            'medications' => [['name' => 'Flea drops', 'usage' => 'Monthly']],
            'allergies' => ['Dust'],
            'vetName' => 'Dr. Hana',
            'vetPhone' => '+20-100-000-0000',
        ],
        'traits' => ['friendly'],
        'additionalInfo' => ['house_trained' => 'yes'],
        'featuredImage' => undecodableJpegUpload('cover.jpg'),
    ];
}

/**
 * `FileManipulator::performConversions()` builds a temporary directory, copies
 * the original into it and deletes it on the way out — with no `try`/`finally`,
 * so a conversion that throws never reaches the delete and the directory is
 * stranded. Conversions run inline here (`QUEUE_CONNECTION=sync`), so the leak
 * is observable in the same process that triggered it.
 *
 * The throw is expected and caught; asserting it happened is what keeps the
 * count comparison from passing vacuously on an upload that decoded fine.
 */
test('a conversion that throws leaves no temporary directory behind', function () {
    Storage::fake(config('media-library.disk_name'));
    $pet = Pet::factory()->create();
    $temporaryDirectoriesBefore = mediaTemporaryDirectoryCount();

    $thrown = null;

    try {
        $pet->addMedia(undecodableJpegUpload())->toMediaCollection(Pet::PHOTO_COLLECTION);
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(CouldNotLoadImage::class)
        ->and(mediaTemporaryDirectoryCount())->toBe($temporaryDirectoriesBefore);
});

/**
 * The same file posted through the listing form. Today the decode failure
 * escapes the create pipeline as a 500 *after* the media row is committed, and
 * `PetMediaResource` reads `getUrl('display')` with no fallback to the original
 * — so the listing renders a URL to a conversion that was never written. A
 * refusal at the validator is the only outcome that leaves nothing behind.
 */
test('rejects an undecodable cover photo with a 422 and stores no media', function () {
    Storage::fake(config('media-library.disk_name'));
    $owner = User::factory()->create();
    $category = Category::factory()->create();

    $this->actingAs($owner)
        ->post(route('pets.store'), undecodablePetPayload($category))
        ->assertInvalid(['featuredImage']);

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk(config('media-library.disk_name'))->allFiles())->toBeEmpty();
});
