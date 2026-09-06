<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

use Closure;

/**
 * Filter by listing type (adoption, sale, mating).
 *
 * The values are the string backing values of App\Enums\ListingType, matching
 * the varchar column.
 *
 * The legacy feed compared integers against the same varchar column and worked:
 * its ListingType enum was int-backed and its factory, seeder and service all
 * stored `$listingType->value`, so the column held "1"/"2"/"3" and an integer
 * whereIn matched. The comparison changed here because the *backing type* did:
 * the port made ListingType string-backed (see .ai/rules/enums.md), so the
 * column now holds "adoption"/"sale"/"mating".
 */
class ApplyListingTypeFilter
{
    public function handle(HomeFeedContext $context, Closure $next): mixed
    {
        if ($context->filters->listingTypes === []) {
            return $next($context);
        }

        $context->query->whereIn('listing_type', $context->filters->listingTypes);

        return $next($context);
    }
}
