<?php

namespace App\Repositories\Eloquent;

use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ConversationRepository implements ConversationRepositoryInterface
{
    public function __construct(protected Conversation $model) {}

    public function getInboxForUser(User $user, ?int $limit = null): Collection
    {
        $query = $user->conversations()
            ->with(['users', 'lastMessage.sender'])
            ->orderByDesc('conversations.last_message_at')
            ->orderByDesc('conversations.updated_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function findDirectConversationBetween(User $user, User $other): ?Conversation
    {
        return $this->model->newQuery()
            ->betweenParticipants($user, $other)
            ->first();
    }

    public function createDirectConversation(User $user, User $other): Conversation
    {
        $conversation = $this->model->newQuery()->create([
            'type' => Conversation::TYPE_DIRECT,
        ]);

        $conversation->users()->attach([
            $user->id => [],
            $other->id => [],
        ]);

        return $conversation->load('users');
    }

    public function loadParticipants(Conversation $conversation): Conversation
    {
        return $conversation->load('users');
    }
}
