<?php

namespace App\Traits;

use App\Models\Like;

trait HasLikes
{
    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable');
    }
}
