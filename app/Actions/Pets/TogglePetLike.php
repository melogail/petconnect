<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Models\User;

/**
 * Toggle a user's like on a listing, returning the resulting liked state.
 *
 * The acting user is passed in explicitly: model code never reads auth(), and
 * liking is what fires App\Observers\LikeObserver, which notifies the owner.
 * Because that write sends a database notification, the route carrying this
 * Action is rate limited.
 */
class TogglePetLike
{
    public function handle(Pet $pet, User $user): bool
    {
        return $pet->toggleLike($user);
    }
}
