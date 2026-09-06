<?php

namespace App\Concerns;

use App\Models\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model a polymorphic like collection.
 *
 * Every mutating helper takes the acting user explicitly: model code never
 * reaches for auth(), so likes behave identically in HTTP, queue and console
 * contexts. Callers (Actions) pass the authenticated user in.
 */
trait HasLikes
{
    /**
     * @return MorphMany<Like, $this>
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /**
     * Like this model on behalf of the given user, ignoring duplicates.
     *
     * createOrFirst() rather than firstOrCreate(): likes carry a unique index
     * on (user_id, likeable_id, likeable_type), so two concurrent taps make
     * firstOrCreate throw UniqueConstraintViolationException. createOrFirst
     * attempts the insert and recovers from that violation by reading the
     * winning row.
     */
    public function like(User $user): Like
    {
        return $this->likes()->createOrFirst([
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * Remove the given user's like, returning whether a like was removed.
     */
    public function unlike(User $user): bool
    {
        return (bool) $this->likes()
            ->whereBelongsTo($user)
            ->delete();
    }

    /**
     * Toggle the given user's like, returning the resulting liked state.
     */
    public function toggleLike(User $user): bool
    {
        if ($this->isLikedBy($user)) {
            $this->unlike($user);

            return false;
        }

        $this->like($user);

        return true;
    }

    public function isLikedBy(User $user): bool
    {
        return $this->likes()
            ->whereBelongsTo($user)
            ->exists();
    }

    /**
     * Flag each result with whether the given user has liked it.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withLikedBy(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query;
        }

        return $query->withExists([
            'likes as is_liked' => fn (Builder $likeQuery): Builder => $likeQuery
                ->whereBelongsTo($user),
        ]);
    }
}
