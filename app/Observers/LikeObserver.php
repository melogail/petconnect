<?php

namespace App\Observers;

use App\Contracts\Likeable;
use App\Models\Like;
use App\Notifications\ModelLikedNotification;

class LikeObserver
{
    /**
     * Handle the Like "created" event.
     */
    public function created(Like $like): void
    {
        $like->loadMissing(['user', 'likeable']);

        $likeable = $like->likeable;

        if (! $likeable instanceof Likeable) {
            return;
        }

        foreach ($likeable->likeNotificationRecipients() as $recipient) {
            if ($recipient === null || $recipient->is($like->user)) {
                continue;
            }

            $recipient->notify(new ModelLikedNotification($like));
        }
    }
}
