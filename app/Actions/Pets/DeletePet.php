<?php

namespace App\Actions\Pets;

use App\Models\Pet;

/**
 * Retire a listing.
 *
 * Pet soft deletes, so the row, its photos and its comment thread survive for
 * moderation and for the owner's own history; nothing is purged here.
 */
class DeletePet
{
    public function handle(Pet $pet): bool
    {
        return (bool) $pet->delete();
    }
}
