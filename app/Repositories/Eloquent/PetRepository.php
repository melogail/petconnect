<?php

namespace App\Repositories\Eloquent;

use App\Models\Pet;
use App\Repositories\Interfaces\PetRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PetRepository implements PetRepositoryInterface
{
    public function __construct(protected Pet $model) {}

    public function all(): Collection
    {
        return $this->model->with(['category', 'breed', 'user'])->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['category', 'breed', 'user'])->paginate($perPage);
    }

    public function find(int $id): ?Pet
    {
        return $this->model->with(['category', 'breed', 'user'])->find($id);
    }

    public function create(array $data): Pet
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->model->findOrFail($id)->update($data);
    }

    public function delete(int $id): bool
    {
        return $this->model->findOrFail($id)->delete();
    }

    /**
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
    ): LengthAwarePaginator {
        $query = $this->homeFeedQuery();

        $this->applyHomeFilters($query, $filters);

        if ($latitude !== null && $longitude !== null && $radiusKm !== null) {
            $query->nearby($latitude, $longitude, $radiusKm);
        } else {
            $query->orderByDesc('created_at');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Base home-feed query with eager loads shared by default and nearby listings.
     */
    protected function homeFeedQuery(): Builder
    {
        return $this->model->newQuery()
            ->available()
            ->with([
                'category',
                'breed',
                'user',
                'comments' => fn ($query) => $query
                    ->whereNull('parent_id')
                    ->with([
                        'user',
                        'replies' => fn ($replyQuery) => $replyQuery
                            ->with('user')
                            ->withReportedByCurrentUser(),
                    ])
                    ->withReportedByCurrentUser()
                    ->latest(),
            ])
            ->withCount(['likes', 'comments'])
            ->withExists([
                'likes as is_liked' => fn ($query) => $query->where('user_id', auth()->id()),
            ]);
    }

    /**
     * @param  array{
     *     category_ids?: list<int>,
     *     breed_ids?: list<int>,
     *     age_min?: float|null,
     *     age_max?: float|null,
     *     listing_types?: list<int>,
     *     vaccinated?: bool|null
     * }  $filters
     */
    protected function applyHomeFilters(Builder $query, array $filters): void
    {
        $categoryIds = array_values(array_unique(array_map('intval', $filters['category_ids'] ?? [])));
        $breedIds = array_values(array_unique(array_map('intval', $filters['breed_ids'] ?? [])));

        if ($categoryIds !== [] && $breedIds !== []) {
            $query->where(function (Builder $builder) use ($categoryIds, $breedIds): void {
                $builder->whereIn('breed_id', $breedIds)
                    ->orWhereIn('category_id', $categoryIds);
            });
        } elseif ($breedIds !== []) {
            $query->whereIn('breed_id', $breedIds);
        } elseif ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        $ageMin = $filters['age_min'] ?? null;
        $ageMax = $filters['age_max'] ?? null;

        if ($ageMin !== null || $ageMax !== null) {
            $query->whereRaw(
                'CAST(age AS DECIMAL(8,2)) BETWEEN ? AND ?',
                [
                    $ageMin ?? 0,
                    $ageMax ?? (float) config('petconnect.filters.max_age_years', 15),
                ]
            );
        }

        $listingTypes = array_values(array_unique(array_map('intval', $filters['listing_types'] ?? [])));

        if ($listingTypes !== []) {
            $query->whereIn('listing_type', $listingTypes);
        }

        if (array_key_exists('vaccinated', $filters) && $filters['vaccinated'] !== null) {
            $query->where('vaccinated', (bool) $filters['vaccinated']);
        }
    }
}
