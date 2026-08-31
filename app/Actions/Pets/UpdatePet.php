<?php

namespace App\Actions\Pets;

use App\Concerns\PetPhotoRules;
use App\Models\Pet;
use App\Pipelines\Pets\Shared\AttachGalleryImages;
use App\Pipelines\Pets\Shared\NormalizeAdditionalInfo;
use App\Pipelines\Pets\Shared\NormalizeBasicAttributes;
use App\Pipelines\Pets\Shared\NormalizeHealthData;
use App\Pipelines\Pets\Shared\NormalizeLocation;
use App\Pipelines\Pets\Shared\NormalizeTraits;
use App\Pipelines\Pets\Shared\RefreshPetWithMedia;
use App\Pipelines\Pets\Shared\ResolveCategoryAndBreed;
use App\Pipelines\Pets\Update\EnsureGalleryCapacity;
use App\Pipelines\Pets\Update\PersistPet;
use App\Pipelines\Pets\Update\RemoveDeletedMedia;
use App\Pipelines\Pets\Update\ReplaceFeaturedImage;
use App\Pipelines\Pets\Update\UpdatePetContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Pipeline\Pipeline;

/**
 * Apply an edit to an existing listing.
 *
 * The edit form posts the whole listing, so it reuses the create flow's
 * Normalize* steps unchanged and differs only in how it persists (an update
 * inside a transaction) and in the photo steps: the gallery has a lifetime cap
 * to respect, the cover photo is replaced rather than added, and the form can
 * ask for gallery photos to be removed.
 *
 * The capacity check runs first, before anything is written or uploaded, so a
 * rejected edit leaves no partial state behind.
 *
 * Among the photo steps, removal runs before attachment so the stored gallery
 * never transiently exceeds the cap the capacity check just approved.
 *
 * This Action is where the flow's one tunable — the lifetime gallery cap — is
 * resolved, so EnsureGalleryCapacity never reads config() and the whole flow can
 * be driven with an explicit value from a test or the console. It is resolved
 * through App\Concerns\PetPhotoRules::maxGalleryImages(), the accessor the
 * `max:` rule and the `photoBounds` prop are both built from, rather than a
 * `config()` call here: this was the third spelling of that key, and the point
 * of the accessor is that the rule, the prop and the flow cannot disagree about
 * how many photos a listing may carry.
 *
 * The write is a full replacement rather than a patch — see
 * Pipelines\Pets\Update\PersistPet for why, and PetDetailResource for the
 * payload a client must round-trip to be safe under it.
 */
class UpdatePet
{
    use PetPhotoRules;

    public function __construct(private readonly Pipeline $pipeline) {}

    /**
     * @param  array<string, mixed>  $data  The validated payload.
     * @param  list<UploadedFile>  $galleryImages
     * @param  list<int>  $deletedMediaIds
     */
    public function handle(
        Pet $pet,
        array $data,
        ?UploadedFile $featuredImage = null,
        array $galleryImages = [],
        array $deletedMediaIds = [],
    ): Pet {
        $context = new UpdatePetContext(
            pet: $pet,
            data: $data,
            featuredImage: $featuredImage,
            galleryImages: $galleryImages,
            deletedMediaIds: $deletedMediaIds,
            maxGalleryImages: $this->maxGalleryImages(),
        );

        return $this->pipeline
            ->send($context)
            ->through([
                EnsureGalleryCapacity::class,
                ResolveCategoryAndBreed::class,
                NormalizeBasicAttributes::class,
                NormalizeLocation::class,
                NormalizeHealthData::class,
                NormalizeTraits::class,
                NormalizeAdditionalInfo::class,
                PersistPet::class,
                ReplaceFeaturedImage::class,
                RemoveDeletedMedia::class,
                AttachGalleryImages::class,
                RefreshPetWithMedia::class,
            ])
            ->then(fn (UpdatePetContext $completed): Pet => $completed->pet());
    }
}
