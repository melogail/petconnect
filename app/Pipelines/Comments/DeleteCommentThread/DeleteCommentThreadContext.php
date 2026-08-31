<?php

namespace App\Pipelines\Comments\DeleteCommentThread;

use App\Models\Comment;
use LogicException;

/**
 * Passable for deleting a comment and everything hanging off it.
 *
 * ## The cascade decision, and why the database is not left to do this alone
 *
 * `comments.parent_id` is `cascadeOnDelete`, so deleting a comment already
 * removes its whole subtree — deeper than the legacy CommentService, which did
 * `$comment->replies()->delete()`, one level only, outside a transaction, and
 * orphaned every grandchild row it left behind.
 *
 * A database-level cascade fires no Eloquent events, and a comment has children
 * the constraint cannot see: `likes` and `reports` reference it polymorphically
 * (`likeable_type` / `reportable_type` = `comment`) with **no foreign key at
 * all**, because a morph column cannot carry one. Deleting a comment therefore
 * strands its likes and its reports, and the cascade strands those of every
 * descendant too — invisibly, because nothing errors.
 *
 * Stranded rows are not merely untidy:
 *
 * - A stranded `reports` row sits in the moderation queue with `reportable`
 *   resolving to null — an item nobody can act on or dismiss, and one more of
 *   them every time a parent is deleted.
 * - Stranded `likes` rows keep being counted by `withCount('likes')` and keep
 *   `isLikedBy()` returning true for a comment nobody can see.
 *
 * Ids are **not** recycled on this schema, and the earlier claim here that a
 * stranded report eventually collides with a genuine one through the
 * `unique (user_id, reportable_type, reportable_id)` index is retracted:
 * `$table->id()` emits `integer primary key autoincrement` on SQLite, verified
 * against the live `comments` and `reports` tables, and AUTOINCREMENT is
 * precisely what stops SQLite reusing a deleted row's id. (The MySQL half —
 * InnoDB losing its counter across a restart — was only ever true before 8.0.)
 * See .ai/rules/migrations.md before repeating it anywhere. The dangling rows
 * above are reason enough on their own, and they are wrong from the moment the
 * parent goes rather than at some later collision.
 *
 * ## What this flow does instead
 *
 * Collect the whole subtree first, delete its polymorphic children explicitly,
 * then delete the root and let the FK cascade take the `comments` rows. The
 * Action wraps the run in a single transaction, so the reactions and the
 * comments go together or not at all — the legacy version had no transaction
 * and could leave a half-deleted thread behind.
 *
 * Two things this deliberately does *not* do:
 *
 * - It does not delete the descendants' `comments` rows itself. The cascade is
 *   correct for those and doing it twice is wasted work. **If Comment ever gains
 *   an observer or soft deletes, that changes**: a cascade would bypass both,
 *   and this flow would have to delete the subtree through Eloquent instead.
 *   The subtree is already collected here, so that change is one step.
 * - It does not touch `media` or `notifications`, which reference no comment.
 *   A new polymorphic child of Comment is a new step in this flow, not another
 *   branch in an existing one.
 */
class DeleteCommentThreadContext
{
    /**
     * Every comment id in the subtree, root first, once CollectCommentSubtree
     * has run.
     *
     * @var list<int>|null
     */
    protected ?array $subtreeIds = null;

    /**
     * Whether the root row was removed, once DeleteCommentRoot has run.
     */
    protected bool $deleted = false;

    public function __construct(
        public readonly Comment $comment,
    ) {}

    /**
     * @param  list<int>  $subtreeIds
     */
    public function setSubtreeIds(array $subtreeIds): void
    {
        $this->subtreeIds = $subtreeIds;
    }

    /**
     * @return list<int>
     *
     * @throws LogicException When read before CollectCommentSubtree has run.
     */
    public function subtreeIds(): array
    {
        if ($this->subtreeIds === null) {
            throw new LogicException(self::class.' has no subtree yet; CollectCommentSubtree must run first.');
        }

        return $this->subtreeIds;
    }

    public function markDeleted(bool $deleted): void
    {
        $this->deleted = $deleted;
    }

    public function deleted(): bool
    {
        return $this->deleted;
    }
}
