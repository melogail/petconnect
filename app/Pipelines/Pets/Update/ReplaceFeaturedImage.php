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
 * **The new photo is attached before the old one is deleted.** PersistPet has
 * already committed by the time this step runs and uploads deliberately happen
 * outside that transaction, so deleting first meant a failed upload or a failed
 * conversion left the listing with no cover photo at all — and a listing with an
 * empty gallery then has no image whatsoever. Attaching first narrows that to a
 * moment where two members carry the `featured` property, which is harmless:
 * Pet::featuredPhoto() filters on the property and takes the first match, and
 * the old row is gone before the step returns.
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

        $replaced = $pet->featuredPhoto();

        $this->attachFeaturedImage->handle($pet, $context->featuredImage);

        $replaced?->delete();
        $pet->unsetRelation('media');

        return $next($context);
    }
}
