<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Load a single listing with everything the detail page renders.
 *
 * The pet is re-queried rather than lazily loaded off the bound model so it
 * comes back through the same scopes the home feed uses (`withCount`,
 * `withLikedBy`) and the API resources see one consistent shape on both pages.
 *
 * Everything a resource walks is eager loaded, media relations included: the
 * owner's and the category's, because PetOwnerResource and
 * PetCategoryOptionResource call getFirstMediaUrl(), and the comment authors'
 * for the same reason. Loading the User without its media still cost a query
 * per rendered avatar.
 *
 * The comment thread is bounded, not complete: the newest
 * `petconnect.pets.detail_comment_page_size` top-level comments, each with its
 * newest `petconnect.pets.detail_reply_preview` replies. `comments_count`
 * carries the true total, so the page can show "N comments" and offer to fetch
 * the rest. Real pagination of a thread belongs to the comments vertical, which
 * will own a paginated endpoint; the bound is here now so no page can ever ship
 * an unbounded thread in the meantime. The per-parent limits compile to a
 * row_number window, so they stay one query each however many comments load.
 *
 * `rootComments` is counted alongside, and it is a different number from
 * `comments_count`: the latter is the whole morphMany, roots and replies
 * together, while `comments.index` — the endpoint that serves page two — pages
 * `rootComments()` alone. A client working out which page to ask for next has
 * to compare roots with roots, and comparing the shipped slice against
 * `comments_count` instead is what made the first "load more" refetch the slice
 * it already held. It is folded into the `withCount()` that was already there,
 * so it is one more correlated subquery on a query being issued anyway and the
 * page's query count is unchanged.
 *
 * The comments carry their own counters and viewer flags — likes, replies,
 * whether this viewer liked or reported each one — because CommentResource
 * emits all four and reads them with `??`, so omitting them would ship a
 * silent zero rather than an error. They are withCount()/withExists()
 * subqueries on queries already being issued, so they cost rows in a result
 * set, not round trips: the payload's query count is unchanged by them.
 *
 * A null viewer is a guest, and every viewer-specific scope is a no-op for them.
 */
class LoadPetDetail
{
    public function handle(Pet $pet, ?User $viewer): Pet
    {
        $commentPageSize = (int) config('petconnect.pets.detail_comment_page_size', 20);
        $replyPreview = (int) config('petconnect.pets.detail_reply_preview', 3);

        return $pet->newQuery()
            ->whereKey($pet->getKey())
            ->with([
                'user.media',
                'category.media',
                'breed',
                'media',
                'comments' => fn (Relation $comments): Relation => $comments
                    ->whereNull('parent_id')
                    ->with([
                        'user.media',
                        'replies' => fn (Relation $replies): Relation => $replies
                            ->with('user.media')
                            ->withCount(['likes', 'replies'])
                            ->withLikedBy($viewer)
                            ->withReportedBy($viewer)
                            ->latest()
                            ->limit($replyPreview),
                    ])
                    ->withCount(['likes', 'replies'])
                    ->withLikedBy($viewer)
                    ->withReportedBy($viewer)
                    ->latest()
                    ->limit($commentPageSize),
            ])
            ->withCount(['likes', 'comments', 'rootComments'])
            ->withLikedBy($viewer)
            ->firstOrFail();
    }
}
