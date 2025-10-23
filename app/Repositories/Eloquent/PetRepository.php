<?php

namespace App\Repositories\Eloquent;

use App\Models\Pet;
use App\Repositories\Interfaces\PetRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PetRepository implements PetRepositoryInterface
{
    protected $model;

    public function __construct(Pet $pet)
    {
        $this->model = $pet;
    }

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
}
