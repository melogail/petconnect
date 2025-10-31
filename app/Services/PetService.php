<?php

namespace App\Services;

use App\Models\Pet;
use App\Repositories\Interfaces\PetRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PetService
{
    protected $petRepository;

    public function __construct(PetRepositoryInterface $petRepository)
    {
        $this->petRepository = $petRepository;
    }

    public function getAllPets(): Collection
    {
        return $this->petRepository->all();
    }

    public function getPaginatedPets(int $perPage = 15): LengthAwarePaginator
    {
        return $this->petRepository->paginate($perPage);
    }

    public function getPetById(int $id): ?Pet
    {
        return $this->petRepository->find($id);
    }

    public function createPet(array $data): Pet
    {
        // Add any business logic here before creating
        return $this->petRepository->create($data);
    }

    public function updatePet(int $id, array $data): bool
    {
        // Add any business logic here before updating
        return $this->petRepository->update($id, $data);
    }

    public function deletePet(int $id): bool
    {
        // Add any business logic here before deleting
        return $this->petRepository->delete($id);
    }
}
