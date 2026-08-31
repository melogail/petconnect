<?php

namespace App\Pipelines\Comments\PublishComment;

use App\Contracts\Commentable;
use App\Exceptions\Comments\CommentingNotSupported;
use Closure;

/**
 * Refuse to write a comment against a model that does not hold a thread.
 *
 * What it decides is the half the controller cannot: whether the *target*,
 * resolved a moment ago from an id in the URL, is a legal place for a comment.
 * App\Enums\Commentable whitelists the wire value; App\Contracts\Commentable is
 * the model's own declaration that it has a thread and knows who to notify. The
 * two can drift the moment somebody adds an enum case, and the failure mode
 * without this check is silent: rows written against a model nothing ever reads
 * them back from, and a NotifyCommentable that has no recipients to ask for.
 *
 * The acting user is not authorized here and never was — that is CommentPolicy,
 * called with $this->authorize() in CommentController, per
 * .ai/rules/controllers.md. This step used to be called AuthorizeCommenting and
 * spent eight lines of its docblock saying so; the name now says it instead.
 *
 * It aborts with a LogicException rather than a validation error because there
 * is no field a user could fix — the mismatch is in the code, not the request.
 *
 * @throws CommentingNotSupported
 */
class RequireCommentThread
{
    public function handle(PublishCommentContext $context, Closure $next): mixed
    {
        $commentable = $context->commentable();

        if (! $commentable instanceof Commentable) {
            throw CommentingNotSupported::for($commentable);
        }

        return $next($context);
    }
}
