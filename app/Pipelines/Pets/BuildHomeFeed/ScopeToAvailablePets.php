<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

use Closure;

/**
 * The home feed only ever shows listings that are still available; an owner
 * marking a pet unavailable is what takes it out of discovery.
 */
class ScopeToAvailablePets
{
    public function handle(HomeFeedContext $context, Closure $next): mixed
    {
        $context->query->available();

        return $next($context);
    }
}
