<?php

namespace App\Pipelines\Pets\Purge;

use App\Models\Pet;
use LogicException;

/**
 * Passable for permanently destroying one listing and everything about it.
 *
 * ## Purge is not delete, and the back office had neither
 *
 * `pets` soft deletes, so `Actions\Pets\DeletePet` **retires** a listing: the
 * row, its photos and its comment thread survive for moderation, which is the
 * right default and stays the default. PetPolicy::forceDelete is false and
 * stays false.
 *
 * But Nova\Actions\DeleteCategory refuses a category that still has listings —
 * counted `withTrashed()`, because a soft-deleted pet keeps its `category_id`
 * and the RESTRICT constraint still sees it — and tells the admin to "move or
 * permanently delete those listings first". There was no way to do the second
 * thing. No force delete, no purge action: the instruction named an operation
 * the back office did not have, and a category whose last listing had been
 * retired could never be removed.
 *
 * ## Why the cascade cannot be trusted with this
 *
 * A hard `$pet->delete()` reaches exactly two of the listing's five kinds of
 * child, and neither of the two is reached the way it needs to be:
 *
 * - **comments** hang off `commentable_type`/`commentable_id`. Those are morph
 *   columns, which cannot carry a foreign key, so nothing cascades them at all.
 *   The whole thread survives the listing.
 * - **likes** and **saves** are the same shape and survive the same way, and
 *   keep being counted by `withCount()`.
 * - **reports** point at the listing's *comments*, not at the listing, and
 *   would be stranded twice over — once when the comments go and once more for
 *   every reply the `comments.parent_id` cascade takes silently.
 * - **media** is a real foreign key, but medialibrary removes the *files* from
 *   `InteractsWithMedia`'s `deleting` hook, and a database cascade fires no
 *   Eloquent events. The rows would go and the bytes would stay on disk
 *   forever with nothing left naming them.
 *
 * So: collect the comment subtree first, clear the polymorphic children
 * explicitly, then `forceDelete()` the listing **through Eloquent** so the
 * medialibrary hook runs. `forceDelete` rather than `delete` because the
 * listing may already be trashed and because medialibrary preserves the files
 * of a merely soft-deleted model.
 *
 * ## Why Profiles\DeleteAccount\PurgeOwnedListings is not lifted for this
 *
 * It looks like the same job and is not. Its own docblock records the
 * assumption it runs under: "the listings' own polymorphic children —
 * comments, likes, saves — are already gone by the time this runs", because
 * DeleteAccount's earlier steps removed them for the whole account. Reusing it
 * on its own would strand exactly the rows this flow exists to clear.
 *
 * ## Two children a listing deliberately does not have
 *
 * `App\Enums\Reviewable` has one case, `user`, and `App\Enums\Reportable` has
 * `comment` and `review`. A listing can be neither reviewed nor reported
 * directly, and Pet uses neither HasReviews nor HasReport, so there is no step
 * for either. If a listing ever becomes reviewable or reportable, that is a new
 * step here — not a branch in an existing one.
 */
class PurgePetContext
{
    /**
     * Every comment id on the listing, roots and descendants, once
     * CollectListingContent has run.
     *
     * @var list<int>|null
     */
    protected ?array $commentIds = null;

    /**
     * Whether the listing row was removed, once PurgeListingRecord has run.
     */
    protected bool $purged = false;

    public function __construct(
        public readonly Pet $pet,
    ) {}

    /**
     * @param  list<int>  $commentIds
     */
    public function setCommentIds(array $commentIds): void
    {
        $this->commentIds = $commentIds;
    }

    /**
     * @return list<int>
     *
     * @throws LogicException When read before CollectListingContent has run.
     */
    public function commentIds(): array
    {
        if ($this->commentIds === null) {
            throw new LogicException(self::class.' has no comment ids yet; CollectListingContent must run first.');
        }

        return $this->commentIds;
    }

    public function markPurged(bool $purged): void
    {
        $this->purged = $purged;
    }

    public function purged(): bool
    {
        return $this->purged;
    }
}
