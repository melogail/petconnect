<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Attach the cover photo of a listing.
 *
 * The cover photo is not a separate collection: it is the member of the `pets`
 * collection carrying the `featured` custom property, which is what
 * Pet::featuredPhoto() and Pet::galleryPhotos() read.
 */
class AttachFeaturedImage
{
    public function __construct(private readonly ResolveMediaOwnerDirectory $resolveOwnerDirectory) {}

    public function handle(Pet $pet, UploadedFile $image): Media
    {
        return $pet->addMedia($image)
            ->usingFileName($image->hashName())
            ->withCustomProperties([
                Pet::FEATURED_PROPERTY => true,
                ...$this->resolveOwnerDirectory->handle($pet),
            ])
            ->toMediaCollection(Pet::PHOTO_COLLECTION);
    }
}
