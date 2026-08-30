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
    public function created(Message $message): void
    {
        $message->conversation?->update([
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
     */
    protected function refreshLastMessageAt(int $conversationId): void
    {
        $conversation = Conversation::query()->find($conversationId);

        if ($conversation === null) {
            return;
        }

        $latestMessage = $conversation->messages()->latest('created_at')->first();

        $conversation->update([
            'last_message_at' => $latestMessage?->created_at,
        ]);
    }
}
