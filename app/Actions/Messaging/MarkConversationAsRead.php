<?php

namespace App\Actions\Messaging;

use App\Models\Conversation;
use App\Models\User;

/**
 * Move a participant's read cursor to now.
 *
 * Read state is `conversation_user.last_read_at` and nothing else: there is no
 * `messages.read_at` column, so marking a thread read is one pivot update
 * rather than a write per message. `Conversation::markAsReadFor()` owns that
 * write and no-ops for a non-participant.
 *
 * It is an Action with one statement in it because the controller must delegate
 * rather than call the model directly, and because this is the seam a read
 * receipt or a "mark all read" would grow from.
 *
 * ## This is why `conversations.show` no longer writes
 *
 * The legacy ConversationController::show called markAsRead inside a GET, which
 * made the page non-idempotent: every render mutated state. Inertia v3 turns
 * that from a purity argument into a bug — link prefetching and instant visits
 * issue real GET requests on hover and on intent, so a hovered inbox row would
 * have marked a thread read that the user never opened, and the unread badge
 * would clear itself as the pointer crossed the list.
 *
 * The cursor therefore moves only on an explicit POST — `conversations.read`,
 * which the thread page fires once it has actually rendered — and inside
 * StartDirectConversation\MarkReadForInitiator, which is itself reached only by
 * a POST. `conversations.show` is now a pure read.
 */
class MarkConversationAsRead
{
    public function handle(Conversation $conversation, User $user): void
    {
        $conversation->markAsReadFor($user);
    }
}
