<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Assign the opaque directory name the media path generator stores the
     * user's files under, so uploads are never addressable by user id.
     */
    public function creating(User $user): void
    {
        if ($user->media_directory_name === null) {
            $user->media_directory_name = (string) random_int(10 ** 15, 10 ** 18);
        }
    }
}
