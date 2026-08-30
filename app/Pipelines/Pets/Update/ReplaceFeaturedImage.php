<?php

namespace App\Pipelines\Pets\Update;

use App\Actions\Pets\AttachFeaturedImage;
use Closure;

/**
 * Swap the cover photo when the form uploaded a new one.
 *
 * Only the member carrying the `featured` custom property is deleted; the rest
 * of the `pets` collection is the gallery and is left alone. Uploading nothing
 * keeps the existing cover photo, which is what an edit form that only changed
 * the description should do.
 *
 * The passable is UpdatePetContext, not the shared PetAttributeContext: this
 * step destroys an existing photo, which is only ever correct for a listing
 * that already has one. Hinting the abstract context would let it accept a
 * CreatePetContext.
 */
class ReplaceFeaturedImage
{
    public function __construct(private readonly AttachFeaturedImage $attachFeaturedImage) {}

    public function handle(UpdatePetContext $context, Closure $next): mixed
    {
        if ($context->featuredImage === null) {
            return $next($context);
        }

        $pet = $context->pet();

        $pet->featuredPhoto()?->delete();
        $pet->unsetRelation('media');

        $this->attachFeaturedImage->handle($pet, $context->featuredImage);

        return $next($context);
    }
}
