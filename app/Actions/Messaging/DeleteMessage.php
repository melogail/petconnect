<?php

namespace App\Actions\Messaging;

use App\Models\Message;

/**
 * Remove a message from its conversation.
 *
 * A soft delete, unlike a comment, which is destroyed outright. A message is
 * one half of a private correspondence and the other party has already read it;
 * keeping the row is what lets a report about it still be investigated, and
 * `messages` has no polymorphic children to strand the way a comment's likes
 * and reports are stranded by a cascade (see
 * Pipelines\Comments\DeleteCommentThread).
 *
 * No pipeline and no transaction: one statement, one row. The bookkeeping that
 * follows is App\Observers\MessageObserver's — it recomputes
 * `conversations.last_message_at` from whatever is left on `deleted`, so
 * removing the newest message in a thread correctly walks the inbox preview
 * back to the one before it, and `restored` puts it back.
 */
class DeleteMessage
{
    public function handle(Message $message): bool
    {
        return (bool) $message->delete();
    }
}
