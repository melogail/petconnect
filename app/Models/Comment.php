<?php

namespace App\Models;

use App\Concerns\HasLikes;
use App\Concerns\HasReport;
use App\Contracts\Likeable;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A comment on a commentable model, optionally a reply to another comment.
 *
 * Implements Likeable so App\Observers\LikeObserver notifies the author when
 * their comment is liked, the same way pet likes behave.
 *
 * @property int $id
 * @property int $user_id
 * @property string $content
 * @property int|null $parent_id
 * @property string $commentable_type
 * @property int $commentable_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'content', 'parent_id', 'commentable_type', 'commentable_id'])]
class Comment extends Model implements Likeable
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasLikes, HasReport;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The comment author is notified when their comment is liked; the
     * LikeObserver drops self-likes before notifying.
     *
     * @return Collection<int, User>
     */
    public function likeNotificationRecipients(): Collection
    {
        $this->loadMissing('user');

        return collect([$this->user])->filter()->values();
    }

    /**
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
}
