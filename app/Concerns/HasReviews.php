<?php

namespace App\Concerns;

use App\Models\Review;
use App\Models\User;
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
     * Cheaper than loading the reviews just to average them in PHP: both are
     * subqueries on the query already being issued, so a rating summary costs
     * no extra round trip and hydrates no Review models.
     *
     * **Its consumer is the profile page** — Actions\Profiles\LoadProfileForDisplay
     * calls it for the header's "4.6 from 23 reviews", which is what this scope
     * was written for and what it had no caller for until that page existed.
     * Anything else needing an average rating should call this rather than
     * loading `reviews` and averaging in PHP; Http\Resources\Profile\ProfileResource
     * reads both keys with `??`, so a loader that drops the scope ships a
     * neutral summary rather than an N+1.
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

    /**
     * Flag each result with whether the given user has already reviewed it.
     *
     * The same shape as HasLikes::withLikedBy() and HasReport::withReportedBy(),
     * and for the same reason: a `withExists()` subquery rides along on a query
     * that is already being issued, so the answer costs no round trip and
     * hydrates no rows.
     *
     * Its consumer is the public profile page. `reviews` is unique on
     * (user_id, reviewable_type, reviewable_id) and
     * Pipelines\Reviews\SubmitReview\EnsureNotAlreadyReviewed refuses a second
     * one, but nothing on the read side said so — the form was offered
     * unconditionally and "you have already reviewed this person" was
     * discoverable only by submitting and reading the error. `has_reviewed` on
     * Http\Resources\Profile\ProfileResource is that fact, available before
     * the user writes anything.
     *
     * A guest gets no key at all, and the resource reads it with `??` for
     * exactly that reason: "has this nobody reviewed it" has no answer.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withReviewedBy(Builder $query, ?User $user): Builder
    {
        if ($user === null) {
            return $query;
        }

        return $query->withExists([
            'reviews as has_reviewed' => fn (Builder $reviewQuery): Builder => $reviewQuery
                ->where('user_id', $user->getKey()),
        ]);
    }
}
