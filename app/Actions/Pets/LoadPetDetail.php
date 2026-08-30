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
                            ->withReportedBy($viewer)
                            ->latest()
                            ->limit($replyPreview),
                    ])
                    ->withReportedBy($viewer)
                    ->latest()
                    ->limit($commentPageSize),
            ])
            ->withCount(['likes', 'comments'])
            ->withLikedBy($viewer)
            ->firstOrFail();
    }
}
