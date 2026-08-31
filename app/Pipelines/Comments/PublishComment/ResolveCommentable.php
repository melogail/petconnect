<?php

namespace App\Pipelines\Comments\PublishComment;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Turn the whitelisted target type and id into the model being commented on.
 *
 * Resolution goes through App\Enums\Commentable::findOrFail(), never a class
 * name taken from the request or the URL — the enum is the whitelist and the
 * morph aliases it is backed by are the same ones the column stores.
 *
 * findOrFail() runs the model's default scopes, and Pet soft deletes, so a
 * trashed listing raises ModelNotFoundException here and the flow stops before
 * anything is written. That 404 is the intended behaviour, not an oversight: a
 * retired listing is indistinguishable from one that never existed as far as a
 * commenter is concerned. The legacy controller behaved the same way — it
 * called the same `Commentable::findOrFail()` inline — so this preserves a rule
 * rather than adding one; what moved is *where* it is decided, from a line in a
 * controller to a step every publish runs, resolved once and handed to the rest
 * of the flow on the context.
 *
 * @throws ModelNotFoundException<Model> When the target is gone or hidden.
 */
class ResolveCommentable
{
    public function handle(PublishCommentContext $context, Closure $next): mixed
    {
        $context->setCommentable(
            $context->commentableType->findOrFail($context->commentableId),
        );

        return $next($context);
    }
}
