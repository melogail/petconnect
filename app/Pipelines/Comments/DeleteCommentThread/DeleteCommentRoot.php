<?php

namespace App\Pipelines\Comments\DeleteCommentThread;

use Closure;

/**
 * Delete the comment the request named, and let the FK cascade take its
 * descendants.
 *
 * Runs last. The polymorphic children are already gone by now, so there is no
 * window in which a like or a report points at a comment that no longer exists
 * — the Action holds the whole flow in one transaction.
 *
 * The descendants' `comments` rows go by `parent_id`'s `cascadeOnDelete`, not
 * by a second delete here. Comment has no observer and does not soft delete, so
 * nothing is skipped by that; see DeleteCommentThreadContext for the note on
 * what has to change if either of those stops being true.
 */
class DeleteCommentRoot
{
    public function handle(DeleteCommentThreadContext $context, Closure $next): mixed
    {
        $context->markDeleted((bool) $context->comment->delete());

        return $next($context);
    }
}
