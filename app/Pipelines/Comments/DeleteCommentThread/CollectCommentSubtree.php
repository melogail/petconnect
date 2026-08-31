<?php

namespace App\Pipelines\Comments\DeleteCommentThread;

use App\Models\Comment;
use Closure;

/**
 * Gather every comment id the delete is about to remove.
 *
 * The polymorphic clean-up steps that follow need the ids the FK cascade is
 * going to take with it, and by the time the cascade has run they are gone —
 * so they are read first, in the same transaction the deletes happen in.
 *
 * The walk is level-by-level rather than recursive: one `whereIn('parent_id')`
 * per level, so it costs the depth of the thread in queries and not one per
 * comment. Publishing caps depth at two (see
 * PublishComment\ValidateParentBelongsToCommentable), which makes this two
 * queries in practice; it is written for arbitrary depth anyway because Nova
 * and future imports write comments without going through that cap, and a
 * missed level here is exactly the orphan class this flow exists to prevent.
 */
class CollectCommentSubtree
{
    public function handle(DeleteCommentThreadContext $context, Closure $next): mixed
    {
        $collected = [$context->comment->getKey()];
        $frontier = $collected;

        while ($frontier !== []) {
            /** @var list<int> $frontier */
            $frontier = Comment::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            $collected = [...$collected, ...$frontier];
        }

        $context->setSubtreeIds(array_values(array_unique($collected)));

        return $next($context);
    }
}
