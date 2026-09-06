<?php

namespace App\Actions\Notifications;

use App\Models\User;

/**
 * Empty a user's notification inbox.
 *
 * One DELETE through the relation, which fills the morph columns from the model
 * — no hand-written `where('notifiable_type', 'user')`, which under the
 * enforced morph map is the comparison that matches zero rows and reports
 * success (.ai/rules/app.md).
 *
 * Nothing is soft deleted and nothing is archived: a notification is a pointer
 * to something that still exists elsewhere — the like, the comment, the review,
 * the report — so clearing the inbox loses no record of anything. That is what
 * makes a single irreversible "delete all" button acceptable here and not, say,
 * on messages.
 *
 * Deliberately clears read *and* unread. "Delete all" that quietly kept the
 * unread ones would leave the badge lit after the user emptied the list.
 *
 * Returns the number of rows removed.
 */
class DeleteAllNotifications
{
    public function handle(User $user): int
    {
        return $user->notifications()->delete();
    }
}
