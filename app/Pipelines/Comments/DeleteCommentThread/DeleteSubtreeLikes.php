<?php

namespace App\Pipelines\Comments\DeleteCommentThread;

use App\Models\Comment;
use App\Models\Like;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Remove the likes filed against every comment in the subtree.
 *
 * `likes.likeable_id` has no foreign key — a morph column cannot carry one — so
 * nothing in the schema removes these when the comment goes. Left behind they
 * keep being counted: `withCount('likes')` keeps returning the dead comment's
 * total and `isLikedBy()` keeps saying true, for text nobody can read any more.
 *
 * That is wrong from the moment the comment goes, which is the whole reason.
 * Do not restate it as "a recycled id inherits the total" — ids are not
 * recycled here (`$table->id()` emits AUTOINCREMENT, see
 * .ai/rules/migrations.md), and that framing made a present, certain bug look
 * like a future, conditional one.
 *
 * One bulk delete for the whole subtree rather than one per comment. Like has an
 * observer, but it only implements `created`, so the bulk delete skips nothing.
 *
 * Its own step rather than a branch in a shared "clean up" step: a further
 * polymorphic child of Comment is then a step added to the flow's list, not an
 * edit to a class that already works.
 */
class DeleteSubtreeLikes
{
    public function handle(DeleteCommentThreadContext $context, Closure $next): mixed
    {
        Like::query()
            ->where('likeable_type', Relation::getMorphAlias(Comment::class))
            ->whereIn('likeable_id', $context->subtreeIds())
            ->delete();

        return $next($context);
    }
}
