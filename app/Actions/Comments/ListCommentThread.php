<?php

namespace App\Actions\Comments;

use App\Concerns\CommentValidationRules;
use App\Contracts\Commentable as CommentableContract;
use App\Enums\Commentable;
use App\Exceptions\Comments\CommentingNotSupported;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * A page of a commentable's thread: top-level comments, newest first, each
 * carrying a preview of its newest replies and the true count of all of them.
 *
 * This is the endpoint the pet payloads were bounded against. `LoadPetDetail`
 * ships the newest `pets.detail_comment_page_size` comments inside the first
 * render and `EagerLoadFeedRelations` ships `pets.feed_comment_preview` in a
 * feed card, both with an accurate `comments_count`, precisely so no page ever
 * carries an unbounded thread; page two of that thread is this Action, and a
 * comment whose replies overflow the preview is expanded through
 * ListCommentReplies.
 *
 * The target is resolved through the Commentable enum rather than from a class
 * name, and through `findOrFail()` rather than a bare `where` on the morph
 * columns, so the visibility rule that applies to the page applies to the
 * thread: a soft-deleted pet's comments 404 instead of being readable from a
 * URL the page itself no longer serves. The comment-bound endpoints get the
 * same rule from Comment::resolveRouteBinding(), which has no target in its URL
 * to resolve one from.
 *
 * The page is then read off the resolved model's own `rootComments()` relation,
 * so the read path builds no morph filter of its own — a hand-written
 * `where('commentable_type', ...)` is the shape that silently matches nothing
 * the day an alias moves, and there is now none on this path. Narrowing to
 * App\Contracts\Commentable first is what makes that relation available and
 * states the same invariant the write path states in RequireCommentThread: an
 * enum case for a model that never opted in is a code bug, not a 404.
 *
 * Everything a CommentResource walks is eager loaded, `user.media` included —
 * the author avatar is a `getFirstMediaUrl()` call and loading the User without
 * its media costs one query per rendered comment. The counters and the viewer
 * flags are `withCount`/`withExists` subqueries on the queries already being
 * run, so they add rows to a result set rather than round trips: the page costs
 * the same number of queries whether it holds one comment or fifty.
 *
 * A null viewer is a guest, and `is_liked` / `has_reported` are simply absent
 * for them; CommentResource defaults both to false.
 *
 * The default page size comes from CommentValidationRules::threadPerPage()
 * rather than a `config()` call here, because Web\PetController ships the same
 * number to the client as `commentBounds.thread_per_page` — the client cannot
 * work out which page to ask for first without it, and a second spelling of the
 * key is how the cursor and the paginator would come to disagree.
 */
class ListCommentThread
{
    use CommentValidationRules;

    /**
     * @return LengthAwarePaginator<int, Comment>
     *
     * @throws ModelNotFoundException<Model> When the target is gone or hidden.
     * @throws CommentingNotSupported When the enum names a model with no thread.
     */
    public function handle(
        Commentable $commentableType,
        int $commentableId,
        ?User $viewer = null,
        ?int $perPage = null,
        ?int $replyPreview = null,
    ): LengthAwarePaginator {
        $commentable = $commentableType->findOrFail($commentableId);

        if (! $commentable instanceof CommentableContract) {
            throw CommentingNotSupported::for($commentable);
        }

        $perPage ??= $this->threadPerPage();
        $replyPreview ??= (int) config('petconnect.comments.reply_preview', 3);

        return $commentable->rootComments()
            ->with([
                'user.media',
                'replies' => fn (Relation $replies): Relation => $replies
                    ->with('user.media')
                    ->withCount(['likes', 'replies'])
                    ->withLikedBy($viewer)
                    ->withReportedBy($viewer)
                    ->latest()
                    ->limit($replyPreview),
            ])
            ->withCount(['likes', 'replies'])
            ->withLikedBy($viewer)
            ->withReportedBy($viewer)
            ->latest()
            ->paginate($perPage);
    }
}
