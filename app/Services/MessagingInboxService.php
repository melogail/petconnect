<?php

namespace App\Services;

use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\UserResource;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use Illuminate\Support\Str;

class MessagingInboxService
{
    public function __construct(protected ConversationRepositoryInterface $conversations) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function inboxFor(User $user): array
    {
        $conversations = $this->conversations->getInboxForUser($user);

        return $conversations->map(function (Conversation $conversation) use ($user): array {
            $peer = $conversation->otherParticipant($user);
            $lastMessage = $conversation->lastMessage;
            $unread = $conversation->isUnreadFor($user);

            return [
                'conversation' => ConversationResource::make($conversation)->resolve(),
                'peer' => $peer ? UserResource::make($peer)->resolve() : null,
                'last_message' => $lastMessage ? MessageResource::make($lastMessage)->resolve() : null,
                'unread' => $unread,
            ];
        })->values()->all();
    }

    /**
     * @return array{unread_count: int, previews: list<array<string, mixed>>}
     */
    public function sharedPropsFor(User $user): array
    {
        $conversations = $this->conversations->getInboxForUser($user, 8);

        $unreadCount = 0;
        $previews = [];

        foreach ($conversations as $conversation) {
            $peer = $conversation->otherParticipant($user);
            $last = $conversation->lastMessage;
            $unread = $conversation->isUnreadFor($user);

            if ($unread) {
                $unreadCount++;
            }

            $previews[] = [
                'conversation_id' => $conversation->id,
                'peer' => [
                    'id' => $peer?->id,
                    'name' => $peer?->name ?? 'Unknown',
                    'avatar' => $peer?->getMedia('users')->first()?->getUrl(),
                ],
                'sender_id' => $last?->sender_id,
                'preview' => Str::limit((string) ($last?->content ?? ''), 100),
                'time' => $conversation->last_message_at?->diffForHumans() ?? '',
                'unread' => $unread,
            ];
        }

        return [
            'unread_count' => $unreadCount,
            'previews' => $previews,
        ];
    }
}
