<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

use Closure;

/**
 * Filter by age in years.
 *
 * `pets.age` is a string column holding a decimal number of years ("0.5", "2"),
 * so the comparison has to cast; a plain string BETWEEN would sort "10" before
 * "2". An open-ended bound is resolved by HomeFeedFilters rather than being
 * left off, which keeps the filter a single BETWEEN; the ceiling it falls back
 * to arrives on the filters object, because a step never reads config().
 */
class ApplyAgeRangeFilter
{
    public function handle(HomeFeedContext $context, Closure $next): mixed
    {
        if (! $context->filters->hasAgeRange()) {
            return $next($context);
        }

        $context->query->whereRaw('CAST(age AS DECIMAL(8,2)) BETWEEN ? AND ?', [
            $context->filters->effectiveAgeMin(),
            $context->filters->effectiveAgeMax(),
        ]);

        return $next($context);
    }
}
