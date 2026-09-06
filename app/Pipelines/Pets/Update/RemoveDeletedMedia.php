<?php

namespace App\Pipelines\Pets\Update;

use Closure;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Remove the gallery photos the edit form asked to delete.
 *
 * The ids are always resolved against this pet's own gallery rather than looked
 * up globally, so a crafted id cannot delete another owner's file: anything that
 * is not already attached to this pet is silently ignored.
 *
 * The set is `Pet::galleryPhotos()` — the same definition EnsureGalleryCapacity
 * counts against — so what the capacity check models is exactly what this step
 * deletes. It also means the cover photo is not deletable through
 * `deletedMediaIds`: it is replaced by uploading a new one (ReplaceFeaturedImage),
 * which is the only path that keeps a listing with a cover photo. Deleting from
 * the whole `pets` collection instead let an owner remove their cover photo
 * through a key the capacity arithmetic did not know about.
 *
 * It runs *before* AttachGalleryImages, so the stored gallery never transiently
 * holds more photos than EnsureGalleryCapacity allows. Nothing enforces that cap
 * at the collection level today — `pets` has no onlyKeepLatest() — but an edit
 * that swaps three photos for three others should not need the cap to be
 * momentarily breachable to work.
 *
 * The explicit loadMissing() matters: ReplaceFeaturedImage runs first and
 * unsets the media relation after swapping the cover photo, so reading
 * galleryPhotos() straight afterwards would be a lazy load — a
 * LazyLoadingViolationException outside production, on exactly the request that
 * uploads a new cover photo and deletes gallery photos at once.
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
        $pet->loadMissing('media');

        $deleted = $pet->galleryPhotos()
            ->whereIn('id', $context->deletedMediaIds)
            ->each(fn (Media $photo) => $photo->delete())
            ->count();

        if ($deleted > 0) {
            $pet->unsetRelation('media');
        }

        return $next($context);
    }
}
