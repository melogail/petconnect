<?php

namespace App\Contracts;

use App\Models\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * A model that users are able to like.
 *
 * App\Concerns\HasLikes supplies likes(); implementing this interface is what
 * opts a model into like notifications, because App\Observers\LikeObserver
 * ignores anything that is not a Likeable. A model using the trait without
 * implementing this interface is likeable but silent — that is a valid choice,
 * so declare it deliberately rather than assuming the trait is enough.
 */
interface Likeable
{
    /**
     * @return MorphMany<Like, static>
     */
    public function likes(): MorphMany;

    /**
     * Toggle the given user's like, returning the resulting liked state.
     *
     * Declared here so App\Actions\Likes\ToggleLike can be one path for every
     * likeable model without type hinting a concrete class or an intersection
     * with Model. HasLikes already supplies it, so no implementation changes.
     *
     * The acting user is a parameter, never auth(): model code behaves the
     * same in HTTP, queue and console contexts.
     */
    public function toggleLike(User $user): bool;

    /**
     * Users who should be notified when this model is liked.
     *
     * @return Collection<int, User>
     */
    public function likeNotificationRecipients(): Collection;
}
