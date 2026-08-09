<?php

namespace App\Policies;

use App\Models\Pet;
use App\Models\User;

class PetPolicy extends Policy
{
    /**
     * Determine whether the user can like the pet.
     */
    public function like(User $user, Pet $pet): bool
    {
        return $user->isVerified();
    }
}
