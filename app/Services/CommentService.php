<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\User;
use App\Pipelines\FilterBadWordsPipeline;
use App\Pipelines\TrimContentPipeline;
use App\Repositories\Eloquent\CommentRepository;
use App\Traits\HasPipeline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CommentService
{
    use HasPipeline;

    /**
     * Create a new class instance.
     */
    public function __construct(protected CommentRepository $comments)
    {
        //
    }

    public function createComment(array $data): Comment
    {
        $content = $this->pipeline($data['content'], [
            TrimContentPipeline::class,
            FilterBadWordsPipeline::class,
        ]);

        return $this->comments->create([
            'user_id' => $data['user_id'],
            'content' => $content,
            'parent_id' => $data['parent_id'] ?? null,
            'commentable_id' => $data['commentable_id'],
            'commentable_type' => $data['commentable_type'],
        ]);
    }

    public function createCommentFor(
        Model $commentable,
        User $author,
        string $content,
        ?int $parentId = null,
    ): Comment {
        return $this->createComment([
            'user_id' => $author->id,
            'content' => $content,
            'parent_id' => $parentId,
            'commentable_id' => $commentable->getKey(),
            'commentable_type' => $commentable::class,
        ]);
    }

    public function updateComment(Comment $comment, string $content): bool
    {
        $content = $this->pipeline($content, [
            TrimContentPipeline::class,
            FilterBadWordsPipeline::class,
        ]);

        return $this->comments->update($comment->id, [
            'content' => $content,
        ]);

    }

    public function deleteComment(Comment $comment): bool
    {
        if ($comment->replies()->exists()) {
            // delete all replies of the comment
            $comment->replies()->delete();
        }

        return $this->comments->delete($comment->id);
    }

    public function getComments(Model $model): Collection
    {
        // TODO: Implement getComments method.
    }

    public function getCommentById(int $id): Comment
    {
        // TODO: Implement getCommentById method.
    }

    public function getCommentsByUser(User $user): Collection
    {
        // TODO: Implement getCommentsByUser method.
    }

    public function getCommentsByModel(Model $model): Collection
    {
        // TODO: Implement getCommentsByModel method.
    }

    public function getCommentsByParentId(int $parentId): Collection
    {
        // TODO: Implement getCommentsByParentId method.
    }

    public function sanitizeComment(string $comment): string
    {
        // TODO: Implement sanitizeComment method.
    }
}
