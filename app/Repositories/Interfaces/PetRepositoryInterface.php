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
}
