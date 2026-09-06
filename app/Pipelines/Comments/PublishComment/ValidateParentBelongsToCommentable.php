<?php

namespace App\Pipelines\Comments\PublishComment;

use App\Exceptions\Comments\InvalidParentComment;
use App\Models\Comment;
use Closure;

/**
 * Decide whether the named parent is a legal thing for this reply to hang off,
 * and put it on the context when it is.
 *
 * One question, three ways to answer it no:
 *
 * 1. The parent row is gone.
 * 2. The parent lives on a different commentable. The legacy Form Request did
 *    cover this one, with a Rule::exists() comparing `commentable_type` against
 *    `$type->modelClass()` — correct there, because the legacy app registered
 *    no morph map and the column held fully qualified class names.
 * 3. The parent is itself a reply. This is the one nothing in the legacy app
 *    asked: its rule had no depth clause, so threads nested without limit and
 *    the legacy delete only ever removed one level of them. Threads are two
 *    levels deep on purpose here — the payload shape is comments-with-replies,
 *    LoadPetDetail and the thread endpoint both load exactly those two levels,
 *    and a grandchild would be written to a row no reader ever walks to.
 *
 * The check is a single query against the target's own thread, so it is the
 * existence check as well — there is no separate `exists` rule in the Form
 * Request to keep in step with it, and no window between the two for the row to
 * disappear in. Nor could there be one: `commentable_type` holds a morph alias
 * in this app, so the legacy rule's where() on a class name would match nothing
 * if it were ported as written.
 *
 * A top-level comment carries no parent and this step is a no-op for it.
 *
 * @throws InvalidParentComment
 */
class ValidateParentBelongsToCommentable
{
    public function handle(PublishCommentContext $context, Closure $next): mixed
    {
        if (! $context->isReply()) {
            return $next($context);
        }

        /** @var Comment|null $parent */
        $parent = $context->commentableAsThread()
            ->comments()
            ->whereKey($context->parentId)
            ->first();

        if ($parent === null) {
            throw InvalidParentComment::notOnThisThread();
        }

        if ($parent->parent_id !== null) {
            throw InvalidParentComment::isItselfAReply();
        }

        $context->setParent($parent);

        return $next($context);
    }
}
