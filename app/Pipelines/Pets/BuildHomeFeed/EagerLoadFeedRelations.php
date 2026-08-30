<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Load everything a feed card renders in one pass.
 *
 * A card shows the owner, the taxonomy, the photos, the like and comment
 * counters, whether the viewer has already liked it, and a preview of the
 * comment thread. Every one of those is eager loaded, including the `media`
 * relation of the *owner* and of the *category*: PetOwnerResource and
 * PetCategoryOptionResource call getFirstMediaUrl(), which walks that relation,
 * so loading the User and the Category without their media still cost one query
 * per card (measured: 54 media queries on a 12-card page). MediaPathGenerator
 * builds URLs from the media row alone, so once loaded the collection costs
 * nothing further.
 *
 * Comment threads are bounded, not complete. A card shows the newest
 * `$context->commentPreview` top-level comments and no replies at all; the true
 * total travels as `comments_count` and the full thread is the detail page's
 * job. Loading the whole thread put every comment of every card in the feed
 * payload, so one listing with 500 comments dominated the response. The bound
 * is applied per parent (Eloquent compiles a row_number window for a limit
 * inside an eager load), so it is one query for all cards, not one per card.
 *
 * A guest viewer makes withLikedBy() and withReportedBy() no-ops, so `is_liked`
 * and `has_reported` are simply absent and the resources default them to false.
 */
class EagerLoadFeedRelations
{
    public function handle(HomeFeedContext $context, Closure $next): mixed
    {
        $viewer = $context->viewer;
        $commentPreview = $context->commentPreview;

        $context->query
            ->with([
                'category.media',
                'breed',
                'user.media',
                'media',
                'comments' => fn (Relation $comments): Relation => $comments
                    ->whereNull('parent_id')
                    ->with('user.media')
                    ->withReportedBy($viewer)
                    ->latest()
                    ->limit($commentPreview),
            ])
            ->withCount(['likes', 'comments'])
            ->withLikedBy($viewer);

        return $next($context);
    }
}
