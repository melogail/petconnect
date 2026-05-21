<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use App\Repositories\Interfaces\MessageRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function __construct(
        protected ConversationRepositoryInterface $conversations,
        protected MessageRepositoryInterface $messages,
    ) {}

    public function findOrCreateDirectConversation(User $user, User $other): Conversation
    {
        return DB::transaction(function () use ($user, $other): Conversation {
            $existing = $this->conversations->findDirectConversationBetween($user, $other);

            if ($existing) {
                return $existing;
            }

            return $this->conversations->createDirectConversation($user, $other);
        });
    }

    public function startDirectConversation(User $user, User $other, ?string $initialMessage = null): Conversation
    {
        $conversation = $this->findOrCreateDirectConversation($user, $other);

        if (filled($initialMessage)) {
            $this->messages->createForConversation($conversation, [
                'sender_id' => $user->id,
                'content' => $initialMessage,
                'type' => Message::TYPE_TEXT,
                'status' => Message::STATUS_SENT,
            ]);
        }

        return $conversation;
    }

    public function markAsRead(Conversation $conversation, User $user): void
    {
        $conversation->markAsReadFor($user);
    }
}
