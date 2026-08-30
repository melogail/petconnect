<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filter by category and breed, ORing the two when both are given.
 *
 * The OR is deliberate and is carried over from the legacy feed: the filter
 * sheet lets a visitor tick "Dogs" and, separately, "Siamese", and expects to
 * see both rather than the empty intersection an AND would produce.
 */
class ApplyCategoryAndBreedFilter
{
    public function handle(HomeFeedContext $context, Closure $next): mixed
    {
        $categoryIds = $context->filters->categoryIds;
        $breedIds = $context->filters->breedIds;

        if ($categoryIds !== [] && $breedIds !== []) {
            $context->query->where(fn (Builder $builder): Builder => $builder
                ->whereIn('breed_id', $breedIds)
                ->orWhereIn('category_id', $categoryIds));

            return $next($context);
        }

        if ($breedIds !== []) {
            $context->query->whereIn('breed_id', $breedIds);

            return $next($context);
        }

        if ($categoryIds !== []) {
            $context->query->whereIn('category_id', $categoryIds);
        }

        return $next($context);
    }
}
