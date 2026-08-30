<?php

namespace App\Concerns;

use App\Models\Save;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model a polymorphic "saved by user" (bookmark) collection.
 *
 * Like HasLikes, every mutating helper takes the acting user explicitly.
 */
trait HasSaves
{
    /**
     * @return MorphMany<Save, $this>
     */
    public function saves(): MorphMany
    {
        return $this->morphMany(Save::class, 'saveable');
    }

    /**
     * Bookmark this model for the given user, ignoring duplicates.
     *
     * createOrFirst() rather than firstOrCreate(): saves carry a unique index
     * on (user_id, saveable_id, saveable_type), so two concurrent taps make
     * firstOrCreate throw UniqueConstraintViolationException. createOrFirst
     * attempts the insert and recovers from that violation by reading the
     * winning row.
     */
    public function addSave(User $user): Save
    {
        return $this->saves()->createOrFirst([
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * Remove the given user's save, returning whether a save was removed.
     */
    public function removeSave(User $user): bool
    {
        return (bool) $this->saves()
            ->whereBelongsTo($user)
            ->delete();
    }

    /**
     * Toggle the given user's save, returning the resulting saved state.
     */
    public function toggleSave(User $user): bool
    {
        if ($this->isSavedBy($user)) {
            $this->removeSave($user);

            return false;
        }

        $this->addSave($user);

        return true;
    }

    public function isSavedBy(User $user): bool
    {
        return $this->saves()
            ->whereBelongsTo($user)
            ->exists();
    }

    /**
     * Flag each result with whether the given user has saved it.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withSavedBy(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query;
        }

        return $query->withExists([
            'saves as is_saved' => fn (Builder $saveQuery): Builder => $saveQuery
                ->whereBelongsTo($user),
        ]);
    }
}
