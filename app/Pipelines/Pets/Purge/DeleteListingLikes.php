<?php

namespace App\Pipelines\Pets\Purge;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Pet;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Remove every like pointing at the listing or at a comment on it.
 *
 * Both are morph columns with no foreign key, so neither is cascaded by
 * anything. Left behind they keep being counted by `withCount('likes')` and
 * keep `isLikedBy()` returning true for content nobody can reach.
 *
 * One DELETE for both sets. `Like` has an observer, but it implements `created`
 * only, so a bulk delete skips nothing — the same reasoning
 * Comments\DeleteCommentThread\DeleteSubtreeLikes and
 * Profiles\DeleteAccount\DeleteContentLikes both record.
 */
class DeleteListingLikes
{
    public function handle(PurgePetContext $context, Closure $next): mixed
    {
        $commentIds = $context->commentIds();

        Like::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('likeable_type', Relation::getMorphAlias(Pet::class))
                ->where('likeable_id', $context->pet->getKey()))
            ->orWhere(fn (Builder $query): Builder => $query
                ->where('likeable_type', Relation::getMorphAlias(Comment::class))
                ->whereIn('likeable_id', $commentIds))
            ->delete();

        return $next($context);
    }
}
