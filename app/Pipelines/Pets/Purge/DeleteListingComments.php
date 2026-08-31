<?php

namespace App\Pipelines\Pets\Purge;

use App\Models\Comment;
use Closure;

/**
 * Remove the listing's whole comment thread.
 *
 * Deleted by the ids CollectListingContent gathered rather than by the morph
 * filter, so roots and descendants go in one statement and nothing depends on
 * the `comments.parent_id` cascade — which fires no Eloquent events and would
 * be the thing hiding a level from every step before this one.
 *
 * A bulk delete is correct here because `Comment` has no observer and does not
 * soft delete. Both of those are load bearing: if either changes, this must
 * become a cursor over the models, exactly as
 * Comments\DeleteCommentThreadContext records for the same reason. The ids are
 * already collected, so that is a one-line change.
 */
class DeleteListingComments
{
    public function handle(PurgePetContext $context, Closure $next): mixed
    {
        $commentIds = $context->commentIds();

        if ($commentIds !== []) {
            Comment::query()->whereKey($commentIds)->delete();
        }

        return $next($context);
    }
}
