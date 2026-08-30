<?php

namespace App\Concerns;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model a polymorphic comment thread.
 */
trait HasComments
{
    /**
     * Every comment on the model, replies included.
     *
     * @return MorphMany<Comment, $this>
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Only the top-level comments; replies hang off Comment::replies().
     *
     * @return MorphMany<Comment, $this>
     */
    public function rootComments(): MorphMany
    {
        return $this->comments()->whereNull('parent_id');
    }
}
