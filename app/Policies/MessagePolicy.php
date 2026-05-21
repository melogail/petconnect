<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Message $message): bool
    {
        return $user->can('view', $message->conversation);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Message $message): bool
    {
        return $message->sender_id === $user->id
            && $user->can('view', $message->conversation);
    }

    public function delete(User $user, Message $message): bool
    {
        return $this->update($user, $message);
    }

    public function restore(User $user, Message $message): bool
    {
        return $this->update($user, $message);
    }

    public function forceDelete(User $user, Message $message): bool
    {
        return $this->delete($user, $message);
    }
}
