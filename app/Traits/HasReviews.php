<?php

namespace App\Traits;

use App\Models\Review;

trait HasReviews
{
    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
}
