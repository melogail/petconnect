<?php

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Mark one of a user's notifications as read.
 *
 * ## Ownership is the query, not a check after it
 *
 * The notification is resolved through `$user->notifications()`, so a row
 * belonging to somebody else is a ModelNotFoundException — a 404 — rather than
 * a row this Action then has to remember to compare an id against. There is no
 * NotificationPolicy for the same reason: the only question is "is this yours",
 * and reading through the relation answers it in the query. A policy would be a
 * second place to get it wrong.
 *
 * The id is a UUID, and it arrives as a plain string parameter rather than
 * through route model binding: `notifications` is the framework's table, not an
 * application model, and binding it would need a morph-aware resolver that
 * still could not scope to the viewer.
 *
 * `markAsRead()` is a no-op on an already-read row (it early-returns when
 * `read_at` is set), so a double tap costs one SELECT and no write.
 */
class MarkNotificationAsRead
{
    /**
     * @throws ModelNotFoundException<DatabaseNotification> When the notification is not this user's.
     */
    public function handle(User $user, string $notificationId): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->whereKey($notificationId)->firstOrFail();

        $notification->markAsRead();

        return $notification;
    }
}
