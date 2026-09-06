<?php

namespace App\Actions\Notifications;

use App\Models\User;

/**
 * Clear a user's unread badge in one statement.
 *
 * One UPDATE against the `unreadNotifications` relation, not a fetch-then-loop.
 * The legacy controller did `$request->user()->unreadNotifications->markAsRead()`
 * — note the property, not the method — which hydrates every unread row into
 * memory and then issues one UPDATE per row: an account with 300 unread
 * notifications paid 301 queries and 300 model instances to set one column.
 * `DatabaseNotificationCollection::markAsRead()` is what does the looping there.
 *
 * The timestamp is written explicitly rather than left to a model event,
 * because a bulk update fires none.
 *
 * Returns how many rows were marked, so the controller can say nothing happened
 * without asking again.
 */
class MarkAllNotificationsAsRead
{
    public function handle(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
