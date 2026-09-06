<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Pipelines\Comments\DeleteCommentThread\CollectCommentSubtree;
use App\Pipelines\Comments\DeleteCommentThread\DeleteCommentRoot;
use App\Pipelines\Comments\DeleteCommentThread\DeleteCommentThreadContext;
use App\Pipelines\Comments\DeleteCommentThread\DeleteSubtreeLikes;
use App\Pipelines\Comments\DeleteCommentThread\DeleteSubtreeReports;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;

/**
 * Delete a comment and everything hanging off it.
 *
 * The transaction is opened here rather than inside a step, because it has to
 * span all four of them: the subtree is read, its likes and reports are
 * deleted, then the root goes and the FK cascade takes the descendants. Any
 * boundary narrower than the whole flow can leave likes or reports pointing at
 * comments that are gone: reports nobody can act on or dismiss, and like counts
 * for text nobody can read. (Not, as this used to say, because a recycled id
 * would collide with the unique index on `reports` — ids are not recycled on
 * this schema; see .ai/rules/migrations.md.) The legacy CommentService had no
 * transaction and deleted exactly one level of replies.
 *
 * Read DeleteCommentThreadContext for the full cascade-versus-polymorphic-
 * orphans reasoning; it is the decision this flow exists to implement.
 *
 * Comments do not soft delete, so this is permanent. That is deliberate and
 * differs from Pet, which soft deletes so a retired listing survives for
 * moderation: a comment's moderation trail is the report, and the reports are
 * being removed here alongside it.
 */
class DeleteComment
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(Comment $comment): bool
    {
        $context = new DeleteCommentThreadContext($comment);

        return DB::transaction(fn (): bool => $this->pipeline
            ->send($context)
            ->through([
                CollectCommentSubtree::class,
                DeleteSubtreeLikes::class,
                DeleteSubtreeReports::class,
                DeleteCommentRoot::class,
            ])
            ->then(fn (DeleteCommentThreadContext $completed): bool => $completed->deleted()));
    }
}
