<?php

namespace App\Pipelines\Comments\PublishComment;

use App\Models\Comment;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Write the comment row.
 *
 * No transaction: this is one INSERT, which is already atomic, and wrapping it
 * bought nothing measurable — the transaction committed before NotifyCommentable
 * ran, so it never made publish-then-notify a unit. The house rule is the one
 * ReviseComment\PersistContent states and .ai/rules/pipelines.md sets the
 * exception to: a transaction is opened by the Action when a flow writes more
 * than one row that must land together (DeleteCommentThread), never by a step
 * around a single statement.
 *
 * The author and the target are stamped from the context, not from the
 * submitted payload: `user_id`, `commentable_type` and `commentable_id` are all
 * in Comment's #[Fillable] because factories fill them, so forwarding a
 * validated request bag straight into create() would let a caller file a
 * comment under someone else's name. Nothing that came off the wire as a value
 * reaches this array except `content`.
 *
 * `commentable_type` is written as the registered morph alias, resolved from
 * the model class rather than spelled out, so it stays correct if a case is
 * renamed in AppServiceProvider::configureMorphMap().
 *
 * The comment is reloaded with the relations its API Resource walks — the
 * author and their avatar media — because the controller serialises the return
 * value straight back, and Model::preventLazyLoading() would otherwise fire on
 * a freshly created model the moment CommentAuthorResource asked for it.
 */
class PersistComment
{
    public function handle(PublishCommentContext $context, Closure $next): mixed
    {
        $commentable = $context->commentable();

        $comment = Comment::create([
            'user_id' => $context->author->getKey(),
            'content' => $context->content(),
            'parent_id' => $context->parent()?->getKey(),
            'commentable_type' => Relation::getMorphAlias($commentable::class),
            'commentable_id' => $commentable->getKey(),
        ]);

        $comment->load('user.media');

        $context->setComment($comment);

        return $next($context);
    }
}
