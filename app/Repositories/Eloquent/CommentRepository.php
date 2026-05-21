<?php

namespace App\Repositories\Eloquent;

use App\Models\Comment;

class CommentRepository
{
    public function __construct(protected Comment $model) {}

    public function all()
    {
        return $this->model->with('user', 'commentable')->get();
    }

    public function paginate(int $perPage = 15)
    {
        return $this->model->with('user', 'commentable')->paginate($perPage);
    }

    public function find(int $id): ?Comment
    {
        return $this->model->with('user', 'commentable')->findOrFail($id);
    }

    public function create(array $data): Comment
    {
        return $this->model->create([
            'user_id' => $data['user_id'],
            'content' => $data['content'],
            'parent_id' => $data['parent_id'] ?? null,
            'commentable_id' => $data['commentable_id'],
            'commentable_type' => $data['commentable_type'],
        ]);
    }

    public function update(int $id, array $data): bool
    {
        return $this->model->findOrFail($id)->update([
            'content' => $data['content'],
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->model->findOrFail($id)->delete();
    }
}
