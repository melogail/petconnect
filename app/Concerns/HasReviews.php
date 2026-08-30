<?php

namespace App\Concerns;

use App\Models\Review;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model a polymorphic collection of reviews written about it.
 */
trait HasReviews
{
    /**
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Add `reviews_count` and `reviews_avg_rate` to each result.
     *
     * Cheaper than loading the reviews just to average them in PHP.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withReviewStats(Builder $query): Builder
    {
        return $query
            ->withCount('reviews')
            ->withAvg('reviews', 'rate');
    }
}
