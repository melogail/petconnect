<?php

namespace App\Actions\Pets;

use App\Enums\PetStatus;
use App\Models\Pet;

/**
 * Flip a listing between available and unavailable, returning the new status.
 */
class TogglePetStatus
{
    public function handle(Pet $pet): PetStatus
    {
        $pet->status = $pet->status === PetStatus::Available
            ? PetStatus::Unavailable
            : PetStatus::Available;

        $pet->save();

        return $pet->status;
    }
}
