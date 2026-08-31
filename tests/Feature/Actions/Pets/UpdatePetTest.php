<?php

use App\Actions\Pets\AttachFeaturedImage;
use App\Actions\Pets\UpdatePet;
use App\Enums\ListingType;
use App\Exceptions\Pets\PetGalleryLimitExceeded;
use App\Models\Category;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The smallest validated payload the update flow accepts, plus overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function updatePetData(Category $category, array $overrides = []): array
{
    return [
        'name' => 'Renamed',
        'category_id' => $category->getKey(),
        'age' => '2',
        'gender' => 'female',
        'color' => 'Black',
        'description' => 'A calm indoor cat.',
        'listing_type' => ListingType::Adoption->value,
        'status' => 'available',
        'location' => ['city' => 'Cairo', 'state' => 'Cairo', 'country' => 'Egypt'],
        ...$overrides,
    ];
}

/**
 * A listing owned by a verified member, filed under its own category.
 */
function listingUnderEdit(string $name = 'Original'): Pet
{
    return Pet::factory()
        ->for(User::factory())
        ->for(Category::factory())
        ->create(['name' => $name]);
}

/**
 * Attach a photo that is part of the gallery, the way AttachGalleryImages does.
 */
function attachUpdateGalleryPhoto(Pet $pet, string $name = 'gallery.jpg'): Media
{
    return $pet->addMedia(UploadedFile::fake()->image($name))
        ->toMediaCollection(Pet::PHOTO_COLLECTION);
}

/**
 * Attach the cover photo through the production path, so it carries the
 * `featured` custom property Pet::galleryPhotos() rejects on.
 */
function attachUpdateFeaturedPhoto(Pet $pet, string $name = 'cover.jpg'): Media
{
    return app(AttachFeaturedImage::class)->handle($pet, UploadedFile::fake()->image($name));
}

beforeEach(function () {
    Storage::fake(config('media-library.disk_name'));
    config(['petconnect.pets.max_gallery_images' => 3]);
});

describe('gallery capacity', function () {
    test('accepts an edit that fills the gallery to the cap, because the cover photo does not count against it', function () {
        $pet = listingUnderEdit();
        $cover = attachUpdateFeaturedPhoto($pet);
        attachUpdateGalleryPhoto($pet, 'a.jpg');
        attachUpdateGalleryPhoto($pet, 'b.jpg');

        $updated = app(UpdatePet::class)->handle(
            $pet,
            updatePetData($pet->category),
            galleryImages: [UploadedFile::fake()->image('c.jpg')],
        );

        expect($updated->galleryPhotos())->toHaveCount(3);
        $this->assertModelExists($cover);
    });

    test('rejects an edit one photo past the cap and writes nothing', function () {
        $pet = listingUnderEdit();
        $existingIds = collect(['a.jpg', 'b.jpg', 'c.jpg'])
            ->map(fn (string $name): int => attachUpdateGalleryPhoto($pet, $name)->getKey())
            ->all();

        $edit = fn () => app(UpdatePet::class)->handle(
            $pet,
            updatePetData($pet->category),
            galleryImages: [UploadedFile::fake()->image('d.jpg')],
        );

        expect($edit)->toThrow(PetGalleryLimitExceeded::class);
        expect($pet->fresh()->name)->toBe('Original');
        expect($pet->fresh()->galleryPhotos()->modelKeys())->toBe($existingIds);
    });

    test('frees capacity for a gallery photo the edit deletes', function () {
        $pet = listingUnderEdit();
        attachUpdateGalleryPhoto($pet, 'a.jpg');
        attachUpdateGalleryPhoto($pet, 'b.jpg');
        $doomed = attachUpdateGalleryPhoto($pet, 'c.jpg');

        $updated = app(UpdatePet::class)->handle(
            $pet,
            updatePetData($pet->category),
            galleryImages: [UploadedFile::fake()->image('d.jpg')],
            deletedMediaIds: [$doomed->getKey()],
        );

        expect($updated->galleryPhotos())->toHaveCount(3);
        $this->assertModelMissing($doomed);
    });

    test('rejects an edit whose deletion credit is a media id belonging to another listing', function () {
        $pet = listingUnderEdit();
        collect(['a.jpg', 'b.jpg', 'c.jpg'])->each(fn (string $name) => attachUpdateGalleryPhoto($pet, $name));
        $strangerPhoto = attachUpdateGalleryPhoto(listingUnderEdit('Stranger'), 'stranger.jpg');

        $edit = fn () => app(UpdatePet::class)->handle(
            $pet,
            updatePetData($pet->category),
            galleryImages: [UploadedFile::fake()->image('d.jpg')],
            deletedMediaIds: [$strangerPhoto->getKey()],
        );

        expect($edit)->toThrow(PetGalleryLimitExceeded::class);
        expect($pet->fresh()->galleryPhotos())->toHaveCount(3);
        $this->assertModelExists($strangerPhoto);
    });

    test('rejects an edit whose deletion credit is the cover photo, which is not a gallery photo', function () {
        $pet = listingUnderEdit();
        $cover = attachUpdateFeaturedPhoto($pet);
        collect(['a.jpg', 'b.jpg', 'c.jpg'])->each(fn (string $name) => attachUpdateGalleryPhoto($pet, $name));

        $edit = fn () => app(UpdatePet::class)->handle(
            $pet,
            updatePetData($pet->category),
            galleryImages: [UploadedFile::fake()->image('d.jpg')],
            deletedMediaIds: [$cover->getKey()],
        );

        expect($edit)->toThrow(PetGalleryLimitExceeded::class);
        expect($pet->fresh()->galleryPhotos())->toHaveCount(3);
    });
});

describe('deleted media', function () {
    test('ignores a media id from another listing and removes only this listing\'s own photo', function () {
        $pet = listingUnderEdit();
        $ownPhoto = attachUpdateGalleryPhoto($pet, 'own.jpg');
        $strangerPet = listingUnderEdit('Stranger');
        $strangerPhoto = attachUpdateGalleryPhoto($strangerPet, 'stranger.jpg');

        app(UpdatePet::class)->handle(
            $pet,
            updatePetData($pet->category),
            deletedMediaIds: [$ownPhoto->getKey(), $strangerPhoto->getKey()],
        );

        $this->assertModelMissing($ownPhoto);
        $this->assertModelExists($strangerPhoto);
        expect($strangerPet->fresh()->galleryPhotos()->modelKeys())->toBe([$strangerPhoto->getKey()]);
    });

    test('keeps the cover photo, which is replaced rather than deleted through this key', function () {
        $pet = listingUnderEdit();
        $cover = attachUpdateFeaturedPhoto($pet);
        $galleryPhoto = attachUpdateGalleryPhoto($pet, 'a.jpg');

        $updated = app(UpdatePet::class)->handle(
            $pet,
            updatePetData($pet->category),
            deletedMediaIds: [$cover->getKey(), $galleryPhoto->getKey()],
        );

        $this->assertModelExists($cover);
        $this->assertModelMissing($galleryPhoto);
        expect($updated->featuredPhoto()?->getKey())->toBe($cover->getKey());
    });

    test('removes a gallery photo in the same edit that replaces the cover photo', function () {
        $pet = listingUnderEdit();
        $oldCover = attachUpdateFeaturedPhoto($pet, 'old-cover.jpg');
        $doomed = attachUpdateGalleryPhoto($pet, 'a.jpg');
        $kept = attachUpdateGalleryPhoto($pet, 'b.jpg');

        $updated = app(UpdatePet::class)->handle(
            $pet,
            updatePetData($pet->category),
            featuredImage: UploadedFile::fake()->image('new-cover.jpg'),
            deletedMediaIds: [$doomed->getKey()],
        );

        $this->assertModelMissing($oldCover);
        $this->assertModelMissing($doomed);
        expect($updated->galleryPhotos()->modelKeys())->toBe([$kept->getKey()]);
    });
});

describe('cover photo', function () {
    test('replaces the cover photo and leaves the gallery alone', function () {
        $pet = listingUnderEdit();
        $oldCover = attachUpdateFeaturedPhoto($pet, 'old-cover.jpg');
        $galleryPhoto = attachUpdateGalleryPhoto($pet, 'a.jpg');

        $updated = app(UpdatePet::class)->handle(
            $pet,
            updatePetData($pet->category),
            featuredImage: UploadedFile::fake()->image('new-cover.jpg'),
        );

        $this->assertModelMissing($oldCover);
        expect($updated->featuredPhoto()?->getKey())->not->toBe($oldCover->getKey())
            ->and($updated->featuredPhoto()?->getCustomProperty(Pet::FEATURED_PROPERTY))->toBeTrue();
        expect($updated->galleryPhotos()->modelKeys())->toBe([$galleryPhoto->getKey()]);
    });

    test('keeps the existing cover photo when the edit uploads none', function () {
        $pet = listingUnderEdit();
        $cover = attachUpdateFeaturedPhoto($pet);

        $updated = app(UpdatePet::class)->handle($pet, updatePetData($pet->category));

        expect($updated->featuredPhoto()?->getKey())->toBe($cover->getKey());
    });
});
