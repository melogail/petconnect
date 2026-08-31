<?php

namespace App\Nova\Policies;

use App\Models\Admin;
use App\Nova\Breed as BreedResource;

/**
 * Authorization for the Breed resource, on the `admin` guard.
 *
 * Full CRUD, and unlike Category the delete is safe to leave with Nova:
 * `pets.breed_id` is `nullOnDelete`, so removing a breed detaches it from its
 * listings instead of refusing. Nothing is stranded and nothing throws.
 */
class BreedPolicy
{
    /**
     * Determine whether the admin can list breeds.
     */
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can view a breed.
     */
    public function view(Admin $admin, BreedResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can create breeds.
     */
    public function create(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can update a breed.
     */
    public function update(Admin $admin, BreedResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can delete a breed.
     *
     * Yes: `pets.breed_id` is nullOnDelete, so affected listings simply lose
     * their breed.
     */
    public function delete(Admin $admin, BreedResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can restore a breed.
     *
     * `breeds` does not soft delete.
     */
    public function restore(Admin $admin, BreedResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can permanently delete a breed.
     */
    public function forceDelete(Admin $admin, BreedResource $resource): bool
    {
        return false;
    }
}
