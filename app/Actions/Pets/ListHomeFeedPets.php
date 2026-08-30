<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Models\User;
use App\Pipelines\Pets\BuildHomeFeed\ApplyAgeRangeFilter;
use App\Pipelines\Pets\BuildHomeFeed\ApplyCategoryAndBreedFilter;
use App\Pipelines\Pets\BuildHomeFeed\ApplyListingTypeFilter;
use App\Pipelines\Pets\BuildHomeFeed\ApplyNearbyOrRecency;
use App\Pipelines\Pets\BuildHomeFeed\ApplyVaccinatedFilter;
use App\Pipelines\Pets\BuildHomeFeed\EagerLoadFeedRelations;
use App\Pipelines\Pets\BuildHomeFeed\HomeFeedContext;
use App\Pipelines\Pets\BuildHomeFeed\HomeFeedFilters;
use App\Pipelines\Pets\BuildHomeFeed\PaginateFeed;
use App\Pipelines\Pets\BuildHomeFeed\ScopeToAvailablePets;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pipeline\Pipeline;

/**
 * The discovery feed on the home page.
 *
 * Composed as a filter chain so a new filter is a new step rather than another
 * branch in a growing method. The viewer is passed in explicitly: the flow
 * flags each card with whether that user has liked it and each comment with
 * whether they have reported it, and a guest simply gets neither flag.
 *
 * This Action is where every configured tunable is resolved — the page size,
 * the age ceiling an open-ended filter falls back to, and how many comments a
 * card previews — so no step reads config() and the whole flow can be driven
 * with explicit values from a test or the console.
 */
class ListHomeFeedPets
{
    public function __construct(private readonly Pipeline $pipeline) {}

    /**
     * @param  array{
     *     category_ids?: list<int>|null,
     *     breed_ids?: list<int>|null,
     *     age_min?: float|null,
     *     age_max?: float|null,
     *     listing_types?: list<string>|null,
     *     vaccinated?: bool|null
     * }  $filters
     * @return LengthAwarePaginator<int, Pet>
     */
    public function handle(
        ?User $viewer = null,
        array $filters = [],
        ?float $latitude = null,
        ?float $longitude = null,
        ?float $radiusKm = null,
        ?int $perPage = null,
    ): LengthAwarePaginator {
        $context = new HomeFeedContext(
            query: Pet::query(),
            filters: HomeFeedFilters::fromArray(
                $filters,
                (float) config('petconnect.filters.max_age_years', 15),
            ),
            viewer: $viewer,
            latitude: $latitude,
            longitude: $longitude,
            radiusKm: $radiusKm,
            perPage: $perPage ?? (int) config('petconnect.pets.feed_per_page', 12),
            commentPreview: (int) config('petconnect.pets.feed_comment_preview', 3),
        );

        return $this->pipeline
            ->send($context)
            ->through([
                ScopeToAvailablePets::class,
                EagerLoadFeedRelations::class,
                ApplyCategoryAndBreedFilter::class,
                ApplyAgeRangeFilter::class,
                ApplyListingTypeFilter::class,
                ApplyVaccinatedFilter::class,
                ApplyNearbyOrRecency::class,
                PaginateFeed::class,
            ])
            ->then(fn (HomeFeedContext $completed): LengthAwarePaginator => $completed->results());
    }
}
