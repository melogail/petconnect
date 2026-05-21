<?php

namespace App\Observers;

use App\Models\Conversation;
use App\Models\Message;

class MessageObserver
{
    public function created(Message $message): void
    {
        $message->conversation->update([
            'last_message_at' => $message->created_at,
        ]);
    }

    public function deleted(Message $message): void
    {
        if ($message->isForceDeleting()) {
            return;
        }

        $this->refreshConversationLastMessageAt($message->conversation_id);
    }

    public function restored(Message $message): void
    {
        $this->refreshConversationLastMessageAt($message->conversation_id);
    }

    protected function refreshConversationLastMessageAt(int $conversationId): void
    {
        $conversation = Conversation::query()->find($conversationId);

        if (! $conversation) {
            return;
        }

        $latest = $conversation->messages()->latest('created_at')->first();

        $conversation->update([
            'last_message_at' => $latest?->created_at,
        ]);
    }
}
