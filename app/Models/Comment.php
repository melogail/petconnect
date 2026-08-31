<?php

namespace App\Models;

use App\Concerns\HasLikes;
use App\Concerns\HasReport;
use App\Contracts\Likeable;
use App\Contracts\Reportable;
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
 * Implements Reportable so the report flow can ask the comment itself who is
 * answerable for it, instead of duck-typing a `user_id` column the way the
 * legacy StoreReportRequest did. See App\Contracts\Reportable.
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
class Comment extends Model implements Likeable, Reportable
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
     * Resolve a route-bound comment only while the thing it was written on is
     * still visible.
     *
     * A comment is a child model with a public parent, and every route that
     * binds `{comment}` — `comments.replies`, `comments.like`,
     * `comments.update`, `comments.destroy` — addresses it by a sequential id
     * with no mention of that parent in the URL. Left to the default binding,
     * those routes served a soft-deleted listing's discussion (the text plus
     * every author's name, username and location) from an id anybody could
     * guess, while `comments.index` and the pet page it hangs off both 404'd.
     * The visibility rule has to be a property of the comment, not of the
     * route shape, or each new comment-bound route re-decides it.
     *
     * `commentable` is a MorphTo, and `whereHasMorph()` *can* reach through one
     * — it is declined rather than unavailable. `whereHasMorph('commentable',
     * '*')` resolves the wildcard with its own `distinct()->pluck()` over the
     * whole comments table, so it buys no query back, and it only finds types
     * that already have rows; an explicit type list instead hardcodes the
     * commentable whitelist here, in the model, where nothing keeps it in step
     * with the morph map. The relation is loaded instead and resolves to null
     * when the target's own global scopes hide it — `Pet` soft deletes, so a
     * trashed listing is null here. Returning null
     * makes Illuminate\Routing\ImplicitRouteBinding raise
     * ModelNotFoundException, i.e. the same 404 the thread endpoint gives,
     * from one place.
     *
     * The cost is one extra query per comment-bound request, and the loaded
     * relation is on the model the controller receives.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        /** @var self|null $comment */
        $comment = parent::resolveRouteBinding($value, $field);

        if ($comment === null) {
            return null;
        }

        $comment->loadMissing('commentable');

        return $comment->commentable === null ? null : $comment;
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
     * The comment's author is answerable for it, so they cannot report it.
     *
     * Same body as likeNotificationRecipients() and deliberately a separate
     * method: "who is told when this is liked" and "who may not report this"
     * happen to be the same person today, and a model that merged them would
     * have no way to say so when they diverge.
     *
     * @return Collection<int, User>
     */
    public function reportSubjects(): Collection
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
