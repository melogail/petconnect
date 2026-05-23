<?php

namespace App\Services;

use App\Enums\ListingType;
use App\Enums\PetStatus;
use App\Http\Requests\StorePetRequest;
use App\Http\Requests\UpdatePetRequest;
use App\Models\Category;
use App\Models\Pet;
use App\Repositories\Interfaces\PetRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /**
     * Create a new pet with comprehensive data processing
     *
     * This method handles:
     * - Basic pet information
     * - Location data with coordinates
     * - Health information (basic and detailed)
     * - Personality traits
     * - Additional custom fields
     * - Media file uploads (featured + gallery)
     */
    public function createPet(StorePetRequest $request): Pet
    {
        $validatedData = $request->validated();

        try {
            // Use database transaction to ensure data integrity for the record itself
            $pet = DB::transaction(function () use ($validatedData) {
                // ==========================================
                // 1. PREPARE BASIC PET DATA
                // ==========================================
                $petData = [
                    // Basic Information
                    'user_id' => auth()->id(),
                    'name' => $validatedData['name'],
                    'type' => $validatedData['type'],
                    'age' => $validatedData['age'],
                    'gender' => $validatedData['gender'],
                    'color' => $validatedData['color'],
                    'weight' => $validatedData['weight'] ?? null,
                    'description' => $validatedData['description'],

                    // Listing Information
                    'listing_type' => $validatedData['listing_type'] ?? ListingType::Adoption,
                    'price' => $validatedData['price'] ?? null,
                    'status' => $validatedData['status'] ?? 'available',
                ];

                // ==========================================
                // 2. PROCESS LOCATION DATA
                // ==========================================
                if (isset($validatedData['location'])) {
                    $location = $validatedData['location'];

                    $petData['address'] = $location['address'] ?? null;
                    $petData['detailed_address'] = $location['detailedAddress'] ?? null;
                    $petData['city'] = $location['city'];
                    $petData['state'] = $location['state'];
                    $petData['postal_code'] = $location['postalCode'] ?? null;
                    $petData['country'] = $location['country'];

                    // Handle coordinates
                    if (isset($location['coordinates'])) {
                        $petData['latitude'] = $location['coordinates']['lat'] ?? null;
                        $petData['longitude'] = $location['coordinates']['lng'] ?? null;
                    }
                }

                // ==========================================
                // 3. PROCESS HEALTH DATA
                // ==========================================
                if (isset($validatedData['health'])) {
                    $health = $validatedData['health'];

                    // Basic health fields
                    $petData['health_status'] = $health['status'] ?? 'healthy';
                    $petData['vaccinated'] = isset($health['vaccinated']) ? (bool) $health['vaccinated'] : false;
                    $petData['spayed_neutered'] = isset($health['spayedNeutered']) ? (bool) $health['spayedNeutered'] : false;
                    $petData['special_needs'] = $health['specialNeeds'] ?? null;
                    $petData['last_vet_visit'] = filled($health['lastVetVisit'] ?? null)
                        ? $health['lastVetVisit']
                        : null;

                    // Healthcare arrays (store as JSON)
                    $petData['vaccinations'] = ! empty($health['vaccinations'])
                        ? array_values(array_filter($health['vaccinations'], fn ($v) => ! empty($v['name'])))
                        : null;

                    $petData['medications'] = ! empty($health['medications'])
                        ? array_values(array_filter($health['medications'], fn ($m) => ! empty($m['name'])))
                        : null;

                    $petData['allergies'] = ! empty($health['allergies'])
                        ? array_values(array_filter($health['allergies'], fn ($a) => ! empty($a)))
                        : null;

                    // Veterinarian information
                    $petData['vet_name'] = $health['vetName'] ?? null;
                    $petData['vet_phone'] = $health['vetPhone'] ?? null;
                }

                // ==========================================
                // 4. PROCESS PERSONALITY TRAITS
                // ==========================================
                $petData['traits'] = (isset($validatedData['traits']) && is_array($validatedData['traits']) && count($validatedData['traits']) > 0)
                    ? array_map('ucfirst', array_values($validatedData['traits']))
                    : null;

                // ==========================================
                // 5. PROCESS ADDITIONAL INFO
                // ==========================================
                $filteredInfo = array_values(array_filter(
                    $validatedData['additionalInfo'] ?? [],
                    fn ($item) => filled($item['key'] ?? null) && filled($item['value'] ?? null)
                ));

                $petData['additional_info'] = ! empty($filteredInfo) ? $filteredInfo : null;

                // ==========================================
                // 6. HANDLE CATEGORY & BREED IDs
                // ==========================================
                $petData['category_id'] = $this->getCategoryIdByType($validatedData['type']);
                $petData['breed_id'] = $validatedData['breed'] ?? null;

                unset($petData['type']);

                // ==========================================
                // 7. CREATE PET RECORD
                // ==========================================
                return $this->petRepository->create($petData);
            });

            // ==========================================
            // 8. HANDLE MEDIA UPLOADS (Spatie Media Library)
            // ==========================================
            // Moved OUTSIDE transaction to prevent "file does not exist" errors on rollback

            // Featured Image
            if ($request->hasFile('featuredImage')) {
                $file = $request->file('featuredImage');
                $extension = $file->extension();
                $fileName = time().'_'.Str::random(10).'_featured.'.$extension;

                $pet->addMediaFromRequest('featuredImage')
                    ->usingFileName($fileName)
                    ->withCustomProperties(['featured' => true])
                    ->toMediaCollection('pets');
            }

            // Gallery Images
            if (! empty($validatedData['images'])) {
                foreach ($validatedData['images'] as $image) {
                    // Generate a unique filename
                    $extension = $image->extension();
                    $fileName = time().'_'.Str::random(10).'_gallery.'.$extension;

                    $pet->addMedia($image)
                        ->usingFileName($fileName)
                        ->toMediaCollection('pets');
                }
            }

            // Reload the model to include media relationships
            return $pet->fresh();

        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error creating pet: '.$e->getMessage());
            \Log::error($e->getTraceAsString());

            // Re-throw the exception so the user (and controller) knows it failed
            throw $e;
        }
    }

    /**
     * Helper method to get category ID by pet type
     * Categories are managed by admins via Nova - no auto-creation
     *
     * @param  string  $id
     *
     * @throws \Exception if category not found
     */
    private function getCategoryIdByType($id): ?int
    {
        // Find category by name (case-insensitive)
        $category = Category::findOrFail($id);

        if (! $category) {
            // Log the missing category
            \Log::warning("Category not found for pet id: {$id}");

            // Throw exception to prevent pet creation with invalid category
            throw new \Exception("The category id '{$id}' does not exist. Please contact an administrator to create it first.");
        }

        return $category->id;
    }

    /**
     * Update an existing pet with comprehensive data processing
     */
    public function updatePet(int $id, UpdatePetRequest $request): Pet
    {
        $validatedData = $request->validated();
        $pet = $this->getPetById($id);

        if (! $pet) {
            throw new \Exception('Pet not found.');
        }

        try {
            // Use database transaction to ensure data integrity for the record itself
            DB::transaction(function () use ($pet, $validatedData) {
                // ==========================================
                // 1. PREPARE BASIC PET DATA
                // ==========================================
                $petData = [
                    // Basic Information
                    'name' => $validatedData['name'],
                    'age' => $validatedData['age'],
                    'gender' => $validatedData['gender'],
                    'color' => $validatedData['color'],
                    'weight' => $validatedData['weight'] ?? null,
                    'description' => $validatedData['description'],

                    // Listing Information
                    'listing_type' => $validatedData['listing_type'] ?? ListingType::Adoption,
                    'price' => $validatedData['price'] ?? null,
                    'status' => $validatedData['status'] ?? 'available',
                ];

                // ==========================================
                // 2. PROCESS LOCATION DATA
                // ==========================================
                if (isset($validatedData['location'])) {
                    $location = $validatedData['location'];

                    $petData['address'] = $location['address'] ?? null;
                    $petData['detailed_address'] = $location['detailedAddress'] ?? null;
                    $petData['city'] = $location['city'];
                    $petData['state'] = $location['state'];
                    $petData['postal_code'] = $location['postalCode'] ?? null;
                    $petData['country'] = $location['country'];

                    // Handle coordinates
                    if (isset($location['coordinates'])) {
                        $petData['latitude'] = $location['coordinates']['lat'] ?? null;
                        $petData['longitude'] = $location['coordinates']['lng'] ?? null;
                    }
                }

                // ==========================================
                // 3. PROCESS HEALTH DATA
                // ==========================================
                if (isset($validatedData['health'])) {
                    $health = $validatedData['health'];

                    // Basic health fields
                    $petData['health_status'] = $health['status'] ?? 'healthy';
                    $petData['vaccinated'] = isset($health['vaccinated']) ? (bool) $health['vaccinated'] : false;
                    $petData['spayed_neutered'] = isset($health['spayedNeutered']) ? (bool) $health['spayedNeutered'] : false;
                    $petData['special_needs'] = $health['specialNeeds'] ?? null;
                    $petData['last_vet_visit'] = filled($health['lastVetVisit'] ?? null)
                        ? $health['lastVetVisit']
                        : null;

                    // Healthcare arrays (store as JSON)
                    $petData['vaccinations'] = ! empty($health['vaccinations'])
                        ? array_values(array_filter($health['vaccinations'], fn ($v) => ! empty($v['name'])))
                        : null;

                    $petData['medications'] = ! empty($health['medications'])
                        ? array_values(array_filter($health['medications'], fn ($m) => ! empty($m['name'])))
                        : null;

                    $petData['allergies'] = ! empty($health['allergies'])
                        ? array_values(array_filter($health['allergies'], fn ($a) => ! empty($a)))
                        : null;

                    // Veterinarian information
                    $petData['vet_name'] = $health['vetName'] ?? null;
                    $petData['vet_phone'] = $health['vetPhone'] ?? null;
                }

                // ==========================================
                // 4. PROCESS PERSONALITY TRAITS
                // ==========================================
                $petData['traits'] = (isset($validatedData['traits']) && is_array($validatedData['traits']) && count($validatedData['traits']) > 0)
                    ? array_map('ucfirst', array_values($validatedData['traits']))
                    : null;

                // ==========================================
                // 5. PROCESS ADDITIONAL INFO
                // ==========================================
                if (array_key_exists('additionalInfo', $validatedData)) {
                    $filteredInfo = array_values(array_filter(
                        $validatedData['additionalInfo'] ?? [],
                        fn ($item) => filled($item['key'] ?? null) && filled($item['value'] ?? null)
                    ));

                    $petData['additional_info'] = ! empty($filteredInfo) ? $filteredInfo : null;
                }

                // ==========================================
                // 6. HANDLE CATEGORY & BREED IDs
                // ==========================================
                if (isset($validatedData['type'])) {
                    $petData['category_id'] = $this->getCategoryIdByType($validatedData['type']);
                }
                if (array_key_exists('breed', $validatedData)) {
                    $petData['breed_id'] = $validatedData['breed'] ?? null;
                }

                // ==========================================
                // 7. UPDATE PET RECORD
                // ==========================================
                $this->petRepository->update($pet->id, $petData);
            });

            // ==========================================
            // 8. HANDLE MEDIA UPLOADS (Spatie Media Library)
            // ==========================================

            // Featured Image
            if ($request->hasFile('featuredImage')) {
                // Find and delete only the existing featured image
                $existingFeatured = $pet->getMedia('pets', ['featured' => true])->first();
                if ($existingFeatured) {
                    $existingFeatured->delete();
                }

                $file = $request->file('featuredImage');
                $extension = $file->extension();
                $fileName = time().'_'.Str::random(10).'_featured.'.$extension;

                $pet->addMediaFromRequest('featuredImage')
                    ->usingFileName($fileName)
                    ->withCustomProperties(['featured' => true])
                    ->toMediaCollection('pets');
            }

            // Gallery Images
            if (! empty($validatedData['images'])) {
                foreach ($validatedData['images'] as $image) {
                    $extension = $image->extension();
                    $fileName = time().'_'.Str::random(10).'_gallery.'.$extension;

                    $pet->addMedia($image)
                        ->usingFileName($fileName)
                        ->toMediaCollection('pets');
                }
            }

            // Handle Media Deletion (if provided by frontend)
            if ($request->has('deletedMediaIds')) {
                $deletedMediaIds = $request->input('deletedMediaIds');
                if (is_array($deletedMediaIds)) {
                    foreach ($deletedMediaIds as $mediaId) {
                        $mediaItem = $pet->getMedia('pets')->firstWhere('id', $mediaId);
                        if ($mediaItem) {
                            $mediaItem->delete();
                        }
                    }
                }
            }

            // Reload the model to include media relationships
            return $pet->fresh();

        } catch (\Exception $e) {
            \Log::error('Error updating pet: '.$e->getMessage());
            \Log::error($e->getTraceAsString());
            throw $e;
        }
    }

    public function deletePet(int $id): bool
    {
        // Add any business logic here before deleting
        return $this->petRepository->delete($id);
    }

    public function toggleStatus(int $id): bool
    {
        $pet = $this->getPetById($id);

        $pet->status = $pet->status === PetStatus::available ? PetStatus::unavailable : PetStatus::available;

        return $pet->save();
    }
}
