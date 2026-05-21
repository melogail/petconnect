<?php

namespace App\Repositories\Interfaces;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ConversationRepositoryInterface
{
    public function getInboxForUser(User $user, ?int $limit = null): Collection;

    public function findDirectConversationBetween(User $user, User $other): ?Conversation;

    public function createDirectConversation(User $user, User $other): Conversation;

    public function loadParticipants(Conversation $conversation): Conversation;
}
