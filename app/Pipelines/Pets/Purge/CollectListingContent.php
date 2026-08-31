<?php

namespace App\Pipelines\Pets\Purge;

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
 * The descendants are walked level by level — one `whereIn('parent_id')` per
 * level, not one query per comment. Publishing caps depth at two, so this is
 * two or three queries in practice; it is written for arbitrary depth anyway
 * because Nova and imports write comments without passing that cap, and a
 * missed level is exactly the orphan class this flow exists to prevent. Same
 * shape as Comments\DeleteCommentThread\CollectCommentSubtree.
 */
class CollectListingContent
{
    public function handle(PurgePetContext $context, Closure $next): mixed
    {
        /** @var list<int> $frontier */
        $frontier = Comment::query()
            ->where('commentable_type', Relation::getMorphAlias(Pet::class))
            ->where('commentable_id', $context->pet->getKey())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $collected = $frontier;

        while ($frontier !== []) {
            /** @var list<int> $frontier */
            $frontier = Comment::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            $collected = [...$collected, ...$frontier];
        }

        $context->setCommentIds(array_values(array_unique($collected)));

        return $next($context);
    }
}
