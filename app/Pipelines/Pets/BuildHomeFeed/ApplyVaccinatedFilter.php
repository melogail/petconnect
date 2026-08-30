<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

use Closure;

/**
 * Filter on the vaccinated flag.
 *
 * A null filter means the visitor did not express a preference, which is not
 * the same as asking for unvaccinated pets, so only a non-null value filters.
 */
class ApplyVaccinatedFilter
{
    public function handle(HomeFeedContext $context, Closure $next): mixed
    {
        if ($context->filters->vaccinated === null) {
            return $next($context);
        }

        $context->query->where('vaccinated', $context->filters->vaccinated);

        return $next($context);
    }
}
