<?php

namespace App\Models;

use App\Enums\ConversationType;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A message thread between users.
 *
 * Read/unread for each participant is tracked via conversation_user.last_read_at
 * (a read cursor), not per message; the pivot is App\Models\ConversationUser,
 * which casts that column to a Carbon. `last_message_at` is maintained by
 * App\Observers\MessageObserver.
 *
 * @property int $id
 * @property ConversationType $type
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['type', 'last_message_at'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<User, $this, ConversationUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user', 'conversation_id', 'user_id')
            ->using(ConversationUser::class)
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasOne<Message, $this>
     */
    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function direct(Builder $query): Builder
    {
        return $query->where('type', ConversationType::Direct);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function forParticipant(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        return $query->whereHas('users', fn (Builder $builder): Builder => $builder->whereKey($userId));
    }

    /**
     * The one direct conversation shared by exactly these two participants.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function betweenParticipants(Builder $query, User|int $firstUser, User|int $secondUser): Builder
    {
        return $query
            ->direct()
            ->forParticipant($firstUser)
            ->forParticipant($secondUser)
            ->has('users', '=', 2);
    }

    public function hasParticipant(User $user): bool
    {
        return $this->users()->whereKey($user->getKey())->exists();
    }

    public function markAsReadFor(User $user): void
    {
        if (! $this->hasParticipant($user)) {
            return;
        }

        $this->users()->updateExistingPivot($user->getKey(), [
            'last_read_at' => now(),
        ]);
    }

    /**
     * The other side of a direct conversation.
     */
    public function otherParticipant(User $user): ?User
    {
        $this->loadMissing('users');

        return $this->users->first(
            fn (User $participant): bool => (int) $participant->getKey() !== (int) $user->getKey()
        );
    }

    public function isUnreadFor(User $user): bool
    {
        $this->loadMissing(['users', 'lastMessage']);

        $lastMessage = $this->lastMessage;

        if ($lastMessage === null) {
            return false;
        }

        if ((int) $lastMessage->sender_id === (int) $user->getKey()) {
            return false;
        }

        $participant = $this->users->firstWhere('id', $user->getKey());

        /** @var Carbon|null $lastReadAt */
        $lastReadAt = $participant?->pivot?->last_read_at;

        if ($lastReadAt === null) {
            return true;
        }

        return $lastMessage->created_at->isAfter($lastReadAt);
    }
}
