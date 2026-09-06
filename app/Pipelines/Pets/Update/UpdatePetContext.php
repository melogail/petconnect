<?php

namespace App\Pipelines\Pets\Update;

use App\Models\Pet;
use App\Pipelines\Pets\PetAttributeContext;
use Illuminate\Http\UploadedFile;

/**
 * Passable for the update pet flow.
 *
 * The listing already exists, so it is set on the context up front and the
 * media steps can run against it. `$deletedMediaIds` are the photos the form
 * asked to remove; they are resolved against this pet's own collection, never
 * looked up globally.
 *
 * `$maxGalleryImages` is the lifetime gallery cap, resolved from configuration
 * once by Actions\Pets\UpdatePet and carried here so EnsureGalleryCapacity can
 * be driven with any value without reaching for config().
 */
class UpdatePetContext extends PetAttributeContext
{
    /**
     * @param  array<string, mixed>  $data  The validated payload.
     * @param  list<UploadedFile>  $galleryImages
     * @param  list<int>  $deletedMediaIds
     */
    public function __construct(
        Pet $pet,
        array $data,
        ?UploadedFile $featuredImage = null,
        array $galleryImages = [],
        public readonly array $deletedMediaIds = [],
        public readonly int $maxGalleryImages = 3,
    ) {
        parent::__construct($data, $featuredImage, $galleryImages);

        $this->setPet($pet);
    }
}
