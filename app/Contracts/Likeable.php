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
     * Users who should be notified when this model is liked.
     *
     * @return Collection<int, User>
     */
    public function likeNotificationRecipients(): Collection;
}
