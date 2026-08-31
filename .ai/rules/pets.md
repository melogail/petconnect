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
