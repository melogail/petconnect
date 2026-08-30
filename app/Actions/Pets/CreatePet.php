<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Models\User;
use App\Pipelines\Pets\Create\AttachFeaturedImage;
use App\Pipelines\Pets\Create\CreatePetContext;
use App\Pipelines\Pets\Create\PersistPet;
use App\Pipelines\Pets\Shared\AttachGalleryImages;
use App\Pipelines\Pets\Shared\NormalizeAdditionalInfo;
use App\Pipelines\Pets\Shared\NormalizeBasicAttributes;
use App\Pipelines\Pets\Shared\NormalizeHealthData;
use App\Pipelines\Pets\Shared\NormalizeLocation;
use App\Pipelines\Pets\Shared\NormalizeTraits;
use App\Pipelines\Pets\Shared\RefreshPetWithMedia;
use App\Pipelines\Pets\Shared\ResolveCategoryAndBreed;
use Illuminate\Http\UploadedFile;
use Illuminate\Pipeline\Pipeline;

/**
 * Publish a new listing.
 *
 * The work is a sequence — resolve the taxonomy, translate the form into
 * columns, write the row, then upload the photos — so it runs as a pipeline
 * over a typed context rather than as one long method. Persistence is
 * transactional and every upload step runs after that commit, because a
 * rollback cannot un-write a file.
 */
class CreatePet
{
    public function __construct(private readonly Pipeline $pipeline) {}

    /**
     * @param  array<string, mixed>  $data  The validated payload.
     * @param  list<UploadedFile>  $galleryImages
     */
    public function handle(
        User $owner,
        array $data,
        ?UploadedFile $featuredImage = null,
        array $galleryImages = [],
    ): Pet {
        $context = new CreatePetContext($owner, $data, $featuredImage, $galleryImages);

        return $this->pipeline
            ->send($context)
            ->through([
                ResolveCategoryAndBreed::class,
                NormalizeBasicAttributes::class,
                NormalizeLocation::class,
                NormalizeHealthData::class,
                NormalizeTraits::class,
                NormalizeAdditionalInfo::class,
                PersistPet::class,
                AttachFeaturedImage::class,
                AttachGalleryImages::class,
                RefreshPetWithMedia::class,
            ])
            ->then(fn (CreatePetContext $completed): Pet => $completed->pet());
    }
}
