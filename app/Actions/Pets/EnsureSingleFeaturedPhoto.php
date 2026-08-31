<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Leave exactly one photo of a listing carrying the `featured` custom property.
 *
 * ## The invariant, and the one writer that could break it
 *
 * The cover photo is not a collection of its own: it is the member of `pets`
 * whose `featured` custom property is true, and `Pet::featuredPhoto()` reads it
 * with `->first()`. The application's own flows keep that to one — CreatePet
 * attaches exactly one, and Update\ReplaceFeaturedImage attaches the new cover
 * before deleting the old, which its docblock calls out as a moment where two
 * photos carry the flag and closes before the step returns.
 *
 * Nova's Images field puts a checkbox on **every** photo, so an admin can tick
 * three and save. `->first()` then picks by collection order, which is not the
 * order the checkboxes were in and is not stable against a reorder or a
 * deletion: the same listing renders a different cover for no visible reason,
 * and `galleryPhotos()` — which rejects *every* flagged photo — quietly drops
 * two images from the gallery as well. What ReplaceFeaturedImage treats as a
 * transient, the back office could make permanent.
 *
 * ## What this does about it
 *
 * It keeps the photo `featuredPhoto()` would have returned and clears the flag
 * on the rest, so the stored data agrees with what every page renders instead
 * of disagreeing invisibly. It does not guess a "better" cover than the one
 * already on screen, and it does not refuse the save: the admin's intent
 * ("these are cover candidates") has a defensible reading, and a 422 out of a
 * checkbox on a media row is a worse experience than a settled cover.
 *
 * It is deliberately not an observer on Media. The rule is Pet's, not
 * medialibrary's — Category and Breed have no featured concept — and a hook
 * that fired per media row would fight itself while a multi-photo save is still
 * in flight.
 *
 * @see Pet::FEATURED_PROPERTY
 */
class EnsureSingleFeaturedPhoto
{
    /**
     * @return int The number of photos demoted.
     */
    public function handle(Pet $pet): int
    {
        $pet->load('media');

        $featured = $pet->getMedia(Pet::PHOTO_COLLECTION, [Pet::FEATURED_PROPERTY => true]);

        if ($featured->count() < 2) {
            return 0;
        }

        $demoted = $featured->slice(1);

        $demoted->each(function (Media $photo): void {
            $photo->forgetCustomProperty(Pet::FEATURED_PROPERTY);
            $photo->save();
        });

        $pet->unsetRelation('media');

        return $demoted->count();
    }
}
