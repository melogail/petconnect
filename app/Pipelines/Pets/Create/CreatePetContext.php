<?php

namespace App\Pipelines\Pets\Create;

use App\Models\User;
use App\Pipelines\Pets\PetAttributeContext;
use Illuminate\Http\UploadedFile;

/**
 * Passable for the create pet flow.
 *
 * Adds the owner the listing is filed under; everything else about translating
 * the form into columns is inherited, so the Normalize* steps are shared with
 * the update flow unchanged.
 */
class CreatePetContext extends PetAttributeContext
{
    /**
     * @param  array<string, mixed>  $data  The validated payload.
     * @param  list<UploadedFile>  $galleryImages
     */
    public function __construct(
        public readonly User $owner,
        array $data,
        ?UploadedFile $featuredImage = null,
        array $galleryImages = [],
    ) {
        parent::__construct($data, $featuredImage, $galleryImages);
    }
}
