<?php

namespace App\Pipelines\Pets\Update;

use App\Models\Pet;
use Closure;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Remove the photos the edit form asked to delete.
 *
 * The ids are always resolved against this pet's own `pets` collection rather
 * than looked up globally, so a crafted id cannot delete another owner's file:
 * anything that is not already attached to this pet is silently ignored.
 *
 * The work is inline rather than delegated to an Action of the same name: it
 * has one caller and is not worth exercising without the pipeline.
 */
class RemoveDeletedMedia
{
    public function handle(UpdatePetContext $context, Closure $next): mixed
    {
        if ($context->deletedMediaIds === []) {
            return $next($context);
        }

        $pet = $context->pet();

        $deleted = $pet->getMedia(Pet::PHOTO_COLLECTION)
            ->whereIn('id', $context->deletedMediaIds)
            ->each(fn (Media $photo) => $photo->delete())
            ->count();

        if ($deleted > 0) {
            $pet->unsetRelation('media');
        }

        return $next($context);
    }
}
