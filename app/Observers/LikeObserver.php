<?php

namespace App\Observers;

use App\Contracts\Likeable;
use App\Models\Like;
use App\Models\User;
use App\Notifications\ModelLikedNotification;

/**
 * Notifies the owners of a liked model, skipping self-likes.
 *
 * Registered with #[ObservedBy] on App\Models\Like.
 */
class LikeObserver
{
    public function created(Like $like): void
    {
        $like->loadMissing(['user', 'likeable']);

        $likeable = $like->likeable;

        if (! $likeable instanceof Likeable) {
            return;
        }

        $likeable->likeNotificationRecipients()
            ->filter(fn (?User $recipient): bool => $recipient !== null && ! $recipient->is($like->user))
            ->each(fn (User $recipient) => $recipient->notify(new ModelLikedNotification($like)));
    }
}
