<?php

namespace App\Pipelines\Pets\Purge;

use App\Actions\Comments\ListCommentSubtreeIds;
use App\Models\Comment;
use App\Models\Pet;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Gather every comment id on the listing, roots and every descendant.
 *
 * The steps that follow clear likes and reports that reference those comments
 * through morph columns, and once the comments are gone the ids are
 * unrecoverable — so they are read first, inside the transaction the Action
 * opened.
 *
 * The roots are found by morph alias rather than by class name:
 * `Relation::enforceMorphMap()` is on, so `commentable_type` holds `pet` and a
 * `where('commentable_type', Pet::class)` would match zero rows and report
 * success. See .ai/rules/app.md.
 *
 * The descendants come from Actions\Comments\ListCommentSubtreeIds — one
 * recursive CTE, one binding per root — the same way
 * Comments\DeleteCommentThread\CollectCommentSubtree and
 * Profiles\DeleteAccount\CollectAccountContent get theirs. This step used to
 * walk the levels itself with one `whereIn('parent_id')` per level and, unlike
 * those two, with no guard of any kind: on cyclic data it spun forever inside
 * the transaction the Action had already opened, holding its locks. The CTE's
 * `union` is distinct, so it terminates whatever the data looks like.
 *
 * Publishing caps depth at two, but Nova and imports write comments without
 * passing that cap, and a missed level is exactly the orphan class this flow
 * exists to prevent — the CTE covers arbitrary depth for free.
 */
class CollectListingContent
{
    public function __construct(private readonly ListCommentSubtreeIds $subtreeIds) {}

    public function handle(PurgePetContext $context, Closure $next): mixed
    {
        $roots = Comment::query()
            ->where('commentable_type', Relation::getMorphAlias(Pet::class))
            ->where('commentable_id', $context->pet->getKey())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $context->setCommentIds($this->subtreeIds->handle(array_values($roots)));

        return $next($context);
    }
}
