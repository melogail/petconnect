<?php

namespace App\Actions;

use App\Models\Review;

class CreateReviewAction
{
    public function make($reviewable, $reviewer, $rating, $comment = null): Review
    {
        return Review::create([
            'reviewable_id' => $reviewable->id,
            'reviewable_type' => get_class($reviewable),
            'user_id' => $reviewer->id,
            'rate' => $rating,
            'comment' => $comment,
        ]);
    }
}
