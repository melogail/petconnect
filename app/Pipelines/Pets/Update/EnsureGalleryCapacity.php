<?php

namespace App\Pipelines\Pets\Update;

use App\Exceptions\Pets\PetGalleryLimitExceeded;
use Closure;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Refuse an edit that would leave the listing with more gallery photos than it
 * is allowed to hold.
 *
 * The Form Request caps `images` per request, which bounds a single upload but
 * not the total: three photos per PUT, repeated, accumulated without limit. The
 * lifetime cap has to be decided against what is already attached, so it lives
 * here rather than in the request.
 *
 * The arithmetic is "already attached, minus the ones this edit deletes, plus
 * the ones it uploads", and the deletions counted are only the ids that really
 * belong to this pet — RemoveDeletedMedia ignores the rest, so counting them
 * would let a crafted id buy extra capacity. The cover photo is excluded on
 * both sides: it is validated on its own key and replaced rather than added.
 *
 * It runs first in the flow, before anything is written or uploaded, so a
 * rejected edit leaves no partial state behind.
 *
 * The passable is UpdatePetContext because only an existing listing can
 * accumulate; a create is fully bounded by the per-request rule. The cap itself
 * arrives on the context, resolved once by Actions\Pets\UpdatePet, so this step
 * reads no configuration.
 *
 * `Pet::galleryPhotos()` is the single definition of "a gallery photo" that this
 * step and RemoveDeletedMedia both work from, so the set counted here is exactly
 * the set that can be deleted.
 *
 * @throws PetGalleryLimitExceeded
 */
class EnsureGalleryCapacity
{
    public function handle(UpdatePetContext $context, Closure $next): mixed
    {
        $allowed = $context->maxGalleryImages;

        $pet = $context->pet();
        $pet->loadMissing('media');

        $gallery = $pet->galleryPhotos();

        $removed = $gallery
            ->filter(fn (Media $photo): bool => in_array((int) $photo->getKey(), $context->deletedMediaIds, true))
            ->count();

        $resulting = $gallery->count() - $removed + count($context->galleryImages);

        if ($resulting > $allowed) {
            throw PetGalleryLimitExceeded::withCounts($resulting, $allowed);
        }

        return $next($context);
    }
}
