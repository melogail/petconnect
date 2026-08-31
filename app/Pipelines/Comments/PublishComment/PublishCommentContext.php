<?php

namespace App\Pipelines\Comments\PublishComment;

use App\Contracts\Commentable;
use App\Enums\Commentable as CommentableType;
use App\Models\Comment;
use App\Models\User;
use App\Pipelines\Comments\CommentContentContext;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Passable for the publish comment flow.
 *
 * The target arrives as the App\Enums\Commentable case plus an id, never as a
 * class name from the request, and is resolved into a model by
 * ResolveCommentable. That is the whole reason the enum exists: a request that
 * could name its own `commentable_type` could write a comment row against any
 * table in the schema.
 *
 * `commentableType` and `commentableId` are what the request sent;
 * `commentable()` is what the database confirmed exists and is visible. Steps
 * after ResolveCommentable read the model.
 */
class PublishCommentContext extends CommentContentContext
{
    /**
     * The resolved target, once ResolveCommentable has run.
     */
    protected ?Model $commentable = null;

    /**
     * The resolved parent, once ValidateParentBelongsToCommentable has run.
     * Null for a top-level comment.
     */
    protected ?Comment $parent = null;

    /**
     * The written comment, once PersistComment has run.
     */
    protected ?Comment $comment = null;

    /**
     * @param  list<string>  $bannedWords
     */
    public function __construct(
        public readonly User $author,
        public readonly CommentableType $commentableType,
        public readonly int $commentableId,
        string $content,
        public readonly ?int $parentId = null,
        array $bannedWords = [],
        string $mask = '****',
    ) {
        parent::__construct($content, $bannedWords, $mask);
    }

    /**
     * Whether the submission is a reply rather than a new top-level comment.
     */
    public function isReply(): bool
    {
        return $this->parentId !== null;
    }

    public function setCommentable(Model $commentable): void
    {
        $this->commentable = $commentable;
    }

    /**
     * @throws LogicException When read before ResolveCommentable has run.
     */
    public function commentable(): Model
    {
        if ($this->commentable === null) {
            throw new LogicException(self::class.' has no commentable yet; ResolveCommentable must run first.');
        }

        return $this->commentable;
    }

    /**
     * The target narrowed to the capability the notify step needs.
     *
     * RequireCommentThread is what guarantees the cast holds, so a step calling
     * this after it runs cannot be handed a model with no thread.
     *
     * @throws LogicException When read before ResolveCommentable has run.
     */
    public function commentableAsThread(): Commentable
    {
        $commentable = $this->commentable();

        if (! $commentable instanceof Commentable) {
            throw new LogicException(self::class.' holds a commentable that is not a '.Commentable::class.'; RequireCommentThread must run first.');
        }

        return $commentable;
    }

    public function setParent(Comment $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * The comment being replied to, or null for a top-level comment.
     */
    public function parent(): ?Comment
    {
        return $this->parent;
    }

    public function setComment(Comment $comment): void
    {
        $this->comment = $comment;
    }

    /**
     * @throws LogicException When read before PersistComment has run.
     */
    public function comment(): Comment
    {
        if ($this->comment === null) {
            throw new LogicException(self::class.' has no comment yet; PersistComment must run first.');
        }

        return $this->comment;
    }
}
