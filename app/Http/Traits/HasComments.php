<?php

namespace App\Http\Traits;

use App\Models\Comment;

trait HasComments
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
