<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

use Closure;

/**
 * Run the composed query.
 *
 * withQueryString() keeps the active filters on the generated page links, so
 * paging through a filtered feed does not silently drop the filters.
 */
class PaginateFeed
{
    public function handle(HomeFeedContext $context, Closure $next): mixed
    {
        $context->setResults(
            $context->query->paginate($context->perPage)->withQueryString(),
        );

        return $next($context);
    }
}
