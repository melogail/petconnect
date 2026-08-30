<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Passable for the home feed flow.
 *
 * Every filter step narrows the same builder, so the query is composed once
 * and each step stays ignorant of the ones around it. The viewer is carried
 * explicitly rather than read from auth(), which is what lets the same flow
 * run for a guest, for a signed-in visitor, and from the console.
 */
class HomeFeedContext
{
    /**
     * The paginated feed, once PaginateFeed has run.
     *
     * @var LengthAwarePaginator<int, Pet>|null
     */
    protected ?LengthAwarePaginator $results = null;

    /**
     * @param  Builder<Pet>  $query
     */
    public function __construct(
        public readonly Builder $query,
        public readonly HomeFeedFilters $filters,
        public readonly ?User $viewer = null,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
        public readonly ?float $radiusKm = null,
        public readonly int $perPage = 12,
        public readonly int $commentPreview = 3,
    ) {}

    /**
     * Whether the visitor supplied a usable location to search around.
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null && $this->radiusKm !== null;
    }

    /**
     * @param  LengthAwarePaginator<int, Pet>  $results
     */
    public function setResults(LengthAwarePaginator $results): void
    {
        $this->results = $results;
    }

    /**
     * @return LengthAwarePaginator<int, Pet>
     *
     * @throws LogicException When read before PaginateFeed has run.
     */
    public function results(): LengthAwarePaginator
    {
        if ($this->results === null) {
            throw new LogicException(self::class.' has no results yet; PaginateFeed must run first.');
        }

        return $this->results;
    }
}
