<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Repositories\Interfaces\MessageRepositoryInterface;

class MessageService
{
    public function __construct(protected MessageRepositoryInterface $messages) {}

    public function send(Conversation $conversation, User $sender, string $content, ?string $type = null): Message
    {
        return $this->messages->createForConversation($conversation, [
            'sender_id' => $sender->id,
            'content' => $content,
            'type' => $type ?: Message::TYPE_TEXT,
            'status' => Message::STATUS_SENT,
        ]);
    }

    public function update(Message $message, string $content): Message
    {
        return $this->messages->updateContent($message, $content);
    }

    public function delete(Message $message): void
    {
        $this->messages->delete($message);
    }
}
