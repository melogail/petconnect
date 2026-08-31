<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Assign the opaque directory name the media path generator stores the
     * user's files under, so uploads are never addressable by user id.
     *
     * **The one place this column is assigned.** Registration, factories,
     * seeders and Nova all create users through Eloquent and therefore all
     * arrive here; nothing sets the column itself. Actions\Users\RegisterUser
     * handles a collision by retrying the create(), which builds a fresh model
     * and so re-enters this method for a new draw — a retry rather than a
     * second assignment site.
     *
     * The draw itself lives on User::freshMediaDirectoryName(), so the range is
     * stated once. Uniqueness is the DB unique index's guarantee: this checks
     * nothing.
     *
     * The null guard is what lets a factory or a test pin the value.
     */
    public function creating(User $user): void
    {
        if ($user->media_directory_name === null) {
            $user->media_directory_name = User::freshMediaDirectoryName();
        }
    }
}
