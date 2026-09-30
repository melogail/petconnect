<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Comments\CreateComment;
use App\Actions\Comments\DeleteComment;
use App\Actions\Comments\ListCommentReplies;
use App\Actions\Comments\ListCommentThread;
use App\Actions\Comments\UpdateComment;
use App\Actions\Likes\CountLikes;
use App\Actions\Likes\ToggleLike;
use App\Concerns\ResolvesRequestUser;
use App\Enums\Commentable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Http\Resources\Comment\CommentResource;
use App\Models\Comment as CommentModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Comment threads, for the mobile app. Same Actions, policies and resource as
 * Web\CommentController; the writes answer with the comment instead of a
 * redirect. See that controller for where each visibility check lives.
 */
class CommentController extends Controller
{
    use ResolvesRequestUser;

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
            viewer: $this->viewer($request),
        ));
    }

    public function replies(
        Request $request,
        CommentModel $comment,
        ListCommentReplies $listCommentReplies,
    ): AnonymousResourceCollection {
        $this->authorize('view', $comment);

        return CommentResource::collection($listCommentReplies->handle(
            comment: $comment,
            viewer: $this->viewer($request),
        ));
    }

    public function store(
        StoreCommentRequest $request,
        Commentable $commentable_type,
        int $commentable_id,
        CreateComment $createComment,
    ): JsonResponse {
        $this->authorize('create', CommentModel::class);

        $comment = $createComment->handle(
            author: $this->actor($request),
            commentableType: $commentable_type,
            commentableId: $commentable_id,
            content: $request->content(),
            parentId: $request->parentId(),
        );

        return CommentResource::make($comment->loadMissing('user.media'))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateCommentRequest $request,
        CommentModel $comment,
        UpdateComment $updateComment,
    ): CommentResource {
        $this->authorize('update', $comment);

        $updateComment->handle($comment, $request->content());

        return CommentResource::make($comment->loadMissing('user.media'));
    }

    public function destroy(CommentModel $comment, DeleteComment $deleteComment): Response
    {
        $this->authorize('delete', $comment);

        $deleteComment->handle($comment);

        return response()->noContent();
    }

    public function toggleLike(
        Request $request,
        CommentModel $comment,
        ToggleLike $toggleLike,
        CountLikes $countLikes,
    ): JsonResponse {
        $this->authorize('like', $comment);

        $liked = $toggleLike->handle($comment, $this->actor($request));

        return response()->json([
            'is_liked' => $liked,
            'likes_count' => $countLikes->handle($comment),
        ]);
    }
}
