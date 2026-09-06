<?php

namespace App\Actions\Likes;

use App\Contracts\Likeable;
use App\Models\User;

/**
 * Toggle a user's like on any likeable model, returning the resulting state.
 *
 * One path for every liker. This replaces Actions\Pets\TogglePetLike, which was
 * a single line delegating to the trait and was fine while pets were the only
 * thing a user could like; comments made it two callers, and a second
 * one-line-delegating Action would have been the moment the like flow started
 * drifting per model — different rate limits, different flash messages,
 * different order of operations.
 *
 * It depends on the App\Contracts\Likeable interface rather than on Pet or
 * Comment, so a new likeable model is a new implementation and not an edit
 * here. The interface is what carries `toggleLike()`, so what this Action can
 * be handed is exactly what the LikeObserver will agree to notify about: a
 * model using App\Concerns\HasLikes *without* implementing Likeable is likeable
 * but silent (see .ai/rules/models.md), and it cannot reach this path at all.
 *
 * The acting user is passed in explicitly, because model code never reads
 * auth(). Liking fires App\Observers\LikeObserver, which sends the recipient a
 * database notification, so every route carrying this Action is rate limited.
 */
class ToggleLike
{
    public function handle(Likeable $likeable, User $user): bool
    {
        return $likeable->toggleLike($user);
    }
}
