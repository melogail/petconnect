<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

use Closure;

/**
 * Order the feed: nearest first when the visitor shared a location, newest
 * first otherwise.
 *
 * nearby() and withDistance() are used as a pair on purpose. nearby() only
 * narrows the rows, adding no select and no order, so the paginator's count
 * query still works; withDistance() adds the `distance` alias the card shows
 * and the ordering. Applying withDistance() alone would return the whole table
 * sorted by distance.
 */
class ApplyNearbyOrRecency
{
    public function handle(HomeFeedContext $context, Closure $next): mixed
    {
        if (! $context->hasCoordinates()) {
            $context->query->orderByDesc('created_at');

            return $next($context);
        }

        $context->query
            ->nearby((float) $context->latitude, (float) $context->longitude, (float) $context->radiusKm)
            ->withDistance((float) $context->latitude, (float) $context->longitude);

        return $next($context);
    }
}
