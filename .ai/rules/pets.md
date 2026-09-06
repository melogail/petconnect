---
paths:
  - 'app/Pipelines/Pets/**'
---

# Pets

## Purging one listing is Pipelines\Pets\Purge — DeleteAccount's PurgeOwnedListings is not liftable
`Actions\Pets\DeletePet` retires a listing (soft delete, row and photos and thread kept for moderation) and stays the default. `Actions\Pets\PurgePet` running `Pipelines\Pets\Purge` is the irreversible one, reachable only from `Nova\Actions\PurgePetListing`.

A hard `$pet->delete()` reaches almost none of it: comments, likes and saves hang off morph columns that carry no foreign key and are not cascaded at all; the reports against those comments would be stranded twice over (once when the comments go, again for every reply `comments.parent_id` takes silently); and media rows cascade but medialibrary removes the *files* from an Eloquent `deleting` hook a database cascade never fires. Steps: CollectListingContent (roots by `Relation::getMorphAlias(Pet::class)`, then the subtree level by level) -> DeleteListingReports -> DeleteListingLikes -> DeleteListingSaves -> DeleteListingComments -> PurgeListingRecord (`forceDelete()` through the model). One transaction, opened by the Action.

`Pipelines\Profiles\DeleteAccount\PurgeOwnedListings` looks like the same job and is not: its own docblock records that the listings' comments, likes and saves are already gone by the time it runs, so reusing it alone would strand exactly those rows.

No step for reviews or reports *on the pet*: `Enums\Reviewable` is `user` only and `Enums\Reportable` is `comment|review`, so a listing is neither. If that changes, it is a new step, not a branch.

This also opened a dead end: `DeleteCategory` told the admin to "move or permanently delete those listings first" while no purge existed anywhere in the back office. Verified end to end — refuse, purge the 8 listings, delete the category.

## An undecodable photo is refused in the pipeline, before anything is written
`image` and `mimes:` both decide on the sniffed mime type, so a file with a genuine JPEG SOI + JFIF header and padding behind it clears the Form Request and only fails when a conversion asks GD for the pixels — inside `addMedia()`, after the listing row, the media row and the stored original are committed. Measured: a 500, an orphan media row, a file on the public disk, and a listing whose `display` URL points at a conversion that was never written (`PetMediaResource` reads `getUrl('display')`, which does not fall back to the original).

`Shared\EnsurePhotosAreDecodable` refuses it first, throwing `Exceptions\Pets\PetPhotoNotDecodable` (a `ValidationException`, per the `PetTaxonomyNotFound` precedent) keyed on `featuredImage` / `images.{i}`. It runs before `PersistPet` in the create flow, and after `EnsureGalleryCapacity` in the update flow — counting photos is cheaper than decoding one, and it still lands before `ReplaceFeaturedImage` deletes the cover photo it was meant to replace.

The decode goes through `App\MediaLibrary\ImageDecodeVerifier`, which loads the file with the same `Spatie\Image\Image` driver the conversion uses, so the check cannot disagree with the converter. The step reads no `config()`; the driver belongs to the verifier it is injected with.

This is the request path only and does not replace the `finally` fix — a queued conversion runs with no request to answer. See .ai/rules/media-library.md.

Still unguarded: `Pipelines\Profiles\UpdateProfile\UploadProfileImage` has the same shape for avatars.
