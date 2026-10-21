<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

use Closure;
use Illuminate\Support\Carbon;

/**
 * Filter by age in years.
 *
 * Age is not stored: `pets.birth_date` is, so the bounds are turned into a
 * date window — a pet aged between 1 and 3 years was born between three years
 * ago and one year ago. A listing with no birth date has no age and is left
 * out whenever a bound is set. The bounds arrive in fractional years from the
 * slider and are resolved to whole months, which is as fine as the slider is.
 *
 * An open-ended bound is resolved by HomeFeedFilters rather than being left
 * off, which keeps the filter a single BETWEEN; the ceiling it falls back to
 * arrives on the filters object, because a step never reads config().
 */
class ApplyAgeRangeFilter
{
    public function handle(HomeFeedContext $context, Closure $next): mixed
    {
        if (! $context->filters->hasAgeRange()) {
            return $next($context);
        }

        $today = Carbon::today();

        $context->query->whereNotNull('birth_date')->whereBetween('birth_date', [
            $today->copy()->subMonths($this->months($context->filters->effectiveAgeMax()))->toDateString(),
            $today->copy()->subMonths($this->months($context->filters->effectiveAgeMin()))->toDateString(),
        ]);

        return $next($context);
    }

    private function months(float $years): int
    {
        return (int) round($years * 12);
    }
}
