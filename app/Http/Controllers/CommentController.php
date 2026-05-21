<?php

namespace App\Http\Controllers;

use App\Enums\Commentable;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Services\CommentService;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function __construct(protected CommentService $commentService)
    {
        //
    }

    public function store(
        StoreCommentRequest $request,
        Commentable $commentable_type,
        int $commentable_id,
    ): RedirectResponse {

        $this->authorize('create', Comment::class);

        $this->commentService->createCommentFor(
            commentable: $commentable_type->findOrFail($commentable_id),
            author: $request->user(),
            content: $request->string('content')->toString(),
            parentId: $request->integer('parent_id') ?: null,
        );

        return back()->with('success', 'Comment created successfully');
    }

    public function update(UpdateCommentRequest $request, Comment $comment): RedirectResponse
    {

        $this->authorize('update', $comment);

        $this->commentService->updateComment($comment, $request->string('content')->toString());

        return back()->with('success', 'Comment updated successfully');
    }

    public function delete(Comment $comment): RedirectResponse
    {

        $this->authorize('delete', $comment);

        $this->commentService->deleteComment($comment);

        return back()->with('success', 'Comment deleted successfully');
    }
}
