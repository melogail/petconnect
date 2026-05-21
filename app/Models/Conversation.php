<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Read/unread for each participant is tracked via conversation_user.last_read_at (read cursor).
 */
class Conversation extends Model
{
    /** @use HasFactory<\Database\Factories\ConversationFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_DIRECT = 'direct';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user', 'conversation_id', 'user_id')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    public function scopeDirect(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_DIRECT);
    }

    public function scopeForParticipant(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $query->whereHas('users', fn (Builder $builder) => $builder->whereKey($userId));
    }

    public function scopeBetweenParticipants(Builder $query, User|int $firstUser, User|int $secondUser): Builder
    {
        return $query
            ->direct()
            ->forParticipant($firstUser)
            ->forParticipant($secondUser)
            ->has('users', '=', 2);
    }

    public function hasParticipant(User $user): bool
    {
        return $this->users()->whereKey($user->id)->exists();
    }

    public function markAsReadFor(User $user): void
    {
        if (! $this->hasParticipant($user)) {
            return;
        }

        $this->users()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
        ]);
    }

    public function otherParticipant(User $user): ?User
    {
        $this->loadMissing('users');

        return $this->users->first(fn (User $u) => (int) $u->id !== (int) $user->id);
    }

    public function isUnreadFor(User $user): bool
    {
        $this->loadMissing(['users', 'lastMessage']);

        $lastMessage = $this->lastMessage;
        if (! $lastMessage) {
            return false;
        }

        if ((int) $lastMessage->sender_id === (int) $user->id) {
            return false;
        }

        $member = $this->users->firstWhere('id', $user->id);
        $lastRead = $member?->pivot?->last_read_at;

        if ($lastRead === null) {
            return true;
        }

        return $lastMessage->created_at->isAfter($lastRead);
    }
}
