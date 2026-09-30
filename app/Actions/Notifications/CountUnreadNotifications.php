<?php

namespace App\Actions\Notifications;

use App\Models\User;

/**
 * The number on the bell, for a client that draws the bell itself.
 */
class CountUnreadNotifications
{
    public function handle(User $user): int
    {
        return $user->unreadNotifications()->count();
    }
}
