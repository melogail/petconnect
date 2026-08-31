<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A page of one comment's replies, newest first.
 *
 * The second half of thread pagination. ListCommentThread pages the top-level
 * comments and hands each of them a bounded preview of its replies plus the
 * true `replies_count`, because a reply preview cannot be paginated per parent
 * inside a single eager load — Eloquent compiles a `limit` there into a
 * `row_number` window, which takes the newest N and offers no cursor into the
 * rest. Expanding one comment's replies is therefore a query of its own, which
 * is what this is.
 *
 * No `replies` eager load: threads are two levels deep by design (see
 * Pipelines\Comments\PublishComment\ValidateParentBelongsToCommentable), so a
 * reply has no children to carry, and CommentResource omits the key entirely
 * rather than emitting an empty list. `replies_count` *is* still selected, so
 * every other key matches what the thread endpoint emits and a client reads a
 * reply from either endpoint with the same code.
 *
 * The target's visibility is not re-derived here, and that is not because the
 * URL implies it: `Comment::resolveRouteBinding()` refuses to bind a comment
 * whose commentable is hidden, so a trashed listing's replies 404 before this
 * Action is reached. Keeping the check there rather than here is what makes it
 * hold for `comments.like`, `comments.update` and `comments.destroy` too — they
 * bind the same parameter and would each have needed their own copy. A caller
 * that hands this Action a comment from somewhere other than a route binding
 * owns that check itself.
 *
 * A null viewer is a guest, and `is_liked` / `has_reported` are absent for them.
 */
class ListCommentReplies
{
    /**
     * @return LengthAwarePaginator<int, Comment>
     */
    public function handle(
        Comment $comment,
        ?User $viewer = null,
        ?int $perPage = null,
    ): LengthAwarePaginator {
        $perPage ??= (int) config('petconnect.comments.replies_per_page', 10);

        return $comment->replies()
            ->with('user.media')
            ->withCount(['likes', 'replies'])
            ->withLikedBy($viewer)
            ->withReportedBy($viewer)
            ->latest()
            ->paginate($perPage);
    }
}
