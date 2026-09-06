<?php

namespace App\Observers;

use App\Models\Conversation;
use App\Models\Message;

/**
 * Keeps conversations.last_message_at in step with the messages table.
 *
 * Registered with #[ObservedBy] on App\Models\Message.
 */
class MessageObserver
{
    /**
     * Advance the conversation cursor to the new message — never backwards.
     *
     * A message written over HTTP always carries `now()` as its `created_at`,
     * so the guard is invisible there. It exists for the writers that choose
     * their own timestamps: MessageSeeder backfills a thread with dated
     * messages, and MessageFactory takes a `created_at` from any test that
     * wants an old message. Assigning unconditionally meant inserting a
     * backdated message walked the inbox preview back in time to it, so a
     * fixture built newest-first would leave every thread claiming its oldest
     * message was its latest. The delete/restore/forceDelete paths recompute
     * from the table instead and may legitimately move the cursor either way.
     */
    public function created(Message $message): void
    {
        $conversation = $message->conversation;

        if ($conversation === null || $message->created_at === null) {
            return;
        }

        if ($conversation->last_message_at !== null
            && $conversation->last_message_at->greaterThanOrEqualTo($message->created_at)) {
            return;
        }

        $conversation->update([
            'last_message_at' => $message->created_at,
        ]);
    }

    public function deleted(Message $message): void
    {
        if ($message->isForceDeleting()) {
            return;
        }

        $this->refreshLastMessageAt($message->conversation_id);
    }

    public function restored(Message $message): void
    {
        $this->refreshLastMessageAt($message->conversation_id);
    }

    public function forceDeleted(Message $message): void
    {
        $this->refreshLastMessageAt($message->conversation_id);
    }

    /**
     * Recompute the conversation cursor from the remaining messages.
     *
     * Ordered by created_at then id: several messages can share a second, and
     * created_at alone would let SQLite pick an arbitrary one as the newest.
     */
    protected function refreshLastMessageAt(int $conversationId): void
    {
        $conversation = Conversation::query()->find($conversationId);

        if ($conversation === null) {
            return;
        }

        $latestMessage = $conversation->messages()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $conversation->update([
            'last_message_at' => $latestMessage?->created_at,
        ]);
    }
}
