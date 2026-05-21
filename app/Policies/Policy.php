<?php

namespace App\Policies;

use App\Models\User;

abstract class Policy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isVerified();
    }

    public function update(User $user, $model): bool
    {
        return $user->isVerified() && $user->id === $model->user_id;
    }

    public function delete(User $user, $model): bool
    {
        return $user->isVerified() && $user->id === $model->user_id;
    }

    public function restore(User $user, $model): bool
    {
        return $user->isVerified() && $user->id === $model->user_id;
    }

    public function forceDelete(User $user, $model): bool
    {
        return $user->isVerified() && $user->id === $model->user_id;
    }
}
