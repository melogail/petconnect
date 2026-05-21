<?php

namespace App\Repositories\Eloquent;

use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Interfaces\MessageRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MessageRepository implements MessageRepositoryInterface
{
    public function __construct(protected Message $model) {}

    public function paginateForConversation(Conversation $conversation, int $perPage = 50): LengthAwarePaginator
    {
        return $conversation->messages()
            ->with('sender')
            ->oldest()
            ->paginate($perPage);
    }

    public function createForConversation(Conversation $conversation, array $attributes): Message
    {
        /** @var Message $message */
        $message = $conversation->messages()->create($attributes);

        return $message->loadMissing('sender');
    }

    public function updateContent(Message $message, string $content): Message
    {
        $message->update([
            'content' => $content,
        ]);

        return $message->refresh();
    }

    public function delete(Message $message): void
    {
        $message->delete();
    }
}
