<?php

namespace App\Pipelines\Comments\DeleteCommentThread;

use App\Actions\Comments\ListCommentSubtreeIds;
use Closure;

/**
 * Gather every comment id the delete is about to remove.
 *
 * The polymorphic clean-up steps that follow need the ids the FK cascade is
 * going to take with it, and by the time the cascade has run they are gone —
 * so they are read first, in the same transaction the deletes happen in.
 *
 * The walk is a single recursive CTE (Actions\Comments\ListCommentSubtreeIds),
 * not the level-by-level loop this step used to run: one query with one binding
 * whatever the depth, instead of one `whereIn('parent_id')` per level growing a
 * PHP array of ids while the delete's transaction is open. Publishing caps depth
 * at two (see PublishComment\ValidateParentBelongsToCommentable), but Nova and
 * future imports write comments without going through that cap, and a missed
 * level here is exactly the orphan class this flow exists to prevent — the CTE
 * covers arbitrary depth for free.
 */
class CollectCommentSubtree
{
    public function __construct(private readonly ListCommentSubtreeIds $subtreeIds) {}

    public function handle(DeleteCommentThreadContext $context, Closure $next): mixed
    {
        $context->setSubtreeIds($this->subtreeIds->handle([(int) $context->comment->getKey()]));

        return $next($context);
    }
}
