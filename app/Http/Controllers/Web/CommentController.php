<?php

namespace App\Http\Controllers\Web;

use App\Actions\Comments\CreateComment;
use App\Actions\Comments\DeleteComment;
use App\Actions\Comments\ListCommentReplies;
use App\Actions\Comments\ListCommentThread;
use App\Actions\Comments\UpdateComment;
use App\Actions\Likes\ToggleLike;
use App\Enums\Commentable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Http\Resources\Comment\CommentResource;
use App\Models\Comment as CommentModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;

/**
 * Comment threads on any commentable model.
 *
 * Every action authorizes through CommentPolicy and then hands the work to one
 * Action or pipeline; no query or business rule lives here.
 *
 * ## Two endpoints return JSON rather than an Inertia page
 *
 * `index` and `replies` are data endpoints, not pages: they exist so a thread
 * that a page has already rendered the first bounded slice of can be paged
 * without a visit. Inertia v3's `useHttp` hook is the client for exactly that,
 * and `CommentResource::collection($paginator)` gives it `data`/`links`/`meta`
 * — paginated collections keep their envelope even though
 * JsonResource::withoutWrapping() is on application-wide (see
 * .ai/rules/resources.md). Every write action redirects back, because those are
 * posted from a page and their result belongs in that page's props.
 *
 * ## Where each check lives
 *
 * The policy decides about the acting user, and nothing else — see
 * CommentPolicy. Whether the *target* may be read or written at all is decided
 * in three places, none of them here, and the split is by how the target is
 * addressed:
 *
 * - `index` and `store` name the target in the URL, so the Action resolves it
 *   through App\Enums\Commentable::findOrFail() and a soft-deleted listing is
 *   a ModelNotFoundException.
 * - `replies`, `update`, `destroy` and `toggleLike` name a comment instead, and
 *   Comment::resolveRouteBinding() refuses to bind one whose commentable is
 *   hidden. The URL never mentions the listing, so the binding is the only
 *   place that can speak for it.
 * - Whether the resolved target holds a thread at all is
 *   PublishComment\RequireCommentThread's.
 *
 * The rule behind all three: an endpoint bound to a child model re-derives its
 * parent's visibility rather than assuming the URL implies it.
 *
 * ## Throttling is on the routes, not in the pipeline
 *
 * `comments` and `comment-likes` are named limiters defined in
 * AppServiceProvider::configureRateLimiters(), following the `pet-likes`
 * precedent. Both writes send a database notification, so an unthrottled tap
 * loop is a notification flood; the legacy app throttled nothing anywhere. They
 * are middleware rather than a pipeline step on purpose: a rate limit's only
 * meaningful outcome is a 429 with Retry-After, which is transport, and
 * .ai/rules/pipelines.md keeps steps HTTP-free.
 */
class CommentController extends Controller
{
    /**
     * A page of a commentable's thread.
     *
     * Public: the pages these threads hang off are public, so a guest reading a
     * shared link sees the discussion on it.
     */
    public function index(
        Request $request,
        Commentable $commentable_type,
        int $commentable_id,
        ListCommentThread $listCommentThread,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', CommentModel::class);

        return CommentResource::collection($listCommentThread->handle(
            commentableType: $commentable_type,
            commentableId: $commentable_id,
            viewer: $request->user(),
        ));
    }

    /**
     * A page of one comment's replies.
     */
    public function replies(
        Request $request,
        CommentModel $comment,
        ListCommentReplies $listCommentReplies,
    ): AnonymousResourceCollection {
        $this->authorize('view', $comment);

        return CommentResource::collection($listCommentReplies->handle(
            comment: $comment,
            viewer: $request->user(),
        ));
    }

    /**
     * Publish a comment or a reply.
     */
    public function store(
        StoreCommentRequest $request,
        Commentable $commentable_type,
        int $commentable_id,
        CreateComment $createComment,
    ): RedirectResponse {
        $this->authorize('create', CommentModel::class);

        $createComment->handle(
            author: $request->user(),
            commentableType: $commentable_type,
            commentableId: $commentable_id,
            content: $request->content(),
            parentId: $request->parentId(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment posted.')]);

        return back();
    }

    /**
     * Apply an edit to a comment.
     */
    public function update(
        UpdateCommentRequest $request,
        CommentModel $comment,
        UpdateComment $updateComment,
    ): RedirectResponse {
        $this->authorize('update', $comment);

        $updateComment->handle($comment, $request->content());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment updated.')]);

        return back();
    }

    /**
     * Delete a comment and everything hanging off it.
     *
     * RESTful `destroy`, not the legacy `delete`: the legacy route was
     * `DELETE comments/{comment}` named `comments.delete` pointing at a method
     * called `delete`, which is the only verb in the app that did not match its
     * HTTP method's convention.
     */
    public function destroy(CommentModel $comment, DeleteComment $deleteComment): RedirectResponse
    {
        $this->authorize('delete', $comment);

        $deleteComment->handle($comment);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment removed.')]);

        return back();
    }

    /**
     * Toggle the viewer's like on a comment.
     *
     * The same Action PetController::toggleLike runs — one like path for every
     * likeable model.
     */
    public function toggleLike(Request $request, CommentModel $comment, ToggleLike $toggleLike): RedirectResponse
    {
        $this->authorize('like', $comment);

        $toggleLike->handle($comment, $request->user());

        return back();
    }
}
