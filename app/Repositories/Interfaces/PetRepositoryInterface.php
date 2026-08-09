<?php

namespace App\Repositories\Interfaces;

use App\Models\Pet;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface PetRepositoryInterface
{
    public function all(): Collection;

    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function find(int $id): ?Pet;

    public function create(array $data): Pet;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;

    /**
     * Paginate available pets for the home feed, optionally ordered by nearby distance.
     *
     * @param  array{
     *     category_ids?: list<int>,
     *     breed_ids?: list<int>,
     *     age_min?: float|null,
     *     age_max?: float|null,
     *     listing_types?: list<int>,
     *     vaccinated?: bool|null
     * }  $filters
     */
    public function paginateHomeFeed(
        ?float $latitude = null,
        ?float $longitude = null,
        ?float $radiusKm = null,
        array $filters = [],
        int $perPage = 12,
    ): LengthAwarePaginator;
}
