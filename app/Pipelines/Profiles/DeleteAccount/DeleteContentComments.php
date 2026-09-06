<?php

namespace App\Pipelines\Profiles\DeleteAccount;

use App\Models\Comment;
use Closure;

/**
 * Remove the comment rows themselves.
 *
 * Runs after the likes and reports that pointed at them, so there is never a
 * moment inside the transaction where a reaction outlives its comment.
 *
 * ## Why the cascade is not enough here
 *
 * `comments.user_id` cascades the account's own comments and
 * `comments.parent_id` cascades their replies — but the comments **other
 * people wrote on the account's listings** hang off `commentable_id`, a morph
 * column with no foreign key, and nothing in the database removes them when the
 * listing goes. Without this step they survive pointing at a `pet` row that no
 * longer exists: unreachable through every loader (they all read through the
 * listing's own relation) and invisible until something counts them.
 *
 * Deleting the whole collected set rather than only that subset is deliberate.
 * It costs one DELETE either way, it does not depend on reasoning about which
 * half the cascade would have taken, and it keeps this step correct if
 * `comments.user_id` ever stops cascading.
 *
 * `Comment` has no observer and does not soft delete, so a bulk delete skips
 * nothing. If either of those changes, this has to become a per-model delete —
 * the ids are already collected, so that is a one-line change here rather than
 * a new step.
 */
class DeleteContentComments
{
    public function handle(DeleteAccountContext $context, Closure $next): mixed
    {
        Comment::query()
            ->whereKey($context->commentIds())
            ->delete();

        return $next($context);
    }
}
