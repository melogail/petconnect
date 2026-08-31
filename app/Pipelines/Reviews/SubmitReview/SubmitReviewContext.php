<?php

namespace App\Pipelines\Reviews\SubmitReview;

use App\Contracts\Reviewable;
use App\Enums\Reviewable as ReviewableType;
use App\Exceptions\Reviews\ReviewingNotSupported;
use App\Models\Review;
use App\Models\User;
use App\Pipelines\Reviews\ReviewContentContext;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Passable for the submit review flow.
 *
 * ## This class is where the first legacy hole is closed for good
 *
 * The legacy route was `POST reviews/store/{type}/{reviewable_id}` and the
 * controller's first line was `$reviewable = $type::find($request->reviewable_id)`
 * — `$type` being a raw URL segment used as a class name in a static call. Any
 * class in the application, or any autoloadable class at all with a `find()`,
 * could be named from the address bar; there was no whitelist, no enum and no
 * validation of the segment.
 *
 * Here the target arrives as an App\Enums\Reviewable case plus an integer id,
 * because `{reviewable_type}` is bound to that enum at the router: an unknown
 * value is a 404 before any controller runs, and this context has no field a
 * class name could occupy. `reviewableType` and `reviewableId` are what the URL
 * carried; `reviewable()` is what the database confirmed exists and is visible.
 * Steps after ResolveReviewable read the model.
 */
class SubmitReviewContext extends ReviewContentContext
{
    /**
     * The resolved target, once ResolveReviewable has run.
     */
    protected ?Model $reviewable = null;

    /**
     * The written review, once PersistReview has run.
     */
    protected ?Review $review = null;

    /**
     * @param  list<string>  $bannedWords
     */
    public function __construct(
        public readonly User $author,
        public readonly ReviewableType $reviewableType,
        public readonly int $reviewableId,
        int $rate,
        ?string $comment = null,
        array $bannedWords = [],
        string $mask = '****',
    ) {
        parent::__construct($rate, $comment, $bannedWords, $mask);
    }

    public function setReviewable(Model $reviewable): void
    {
        $this->reviewable = $reviewable;
    }

    /**
     * @throws LogicException When read before ResolveReviewable has run.
     */
    public function reviewable(): Model
    {
        if ($this->reviewable === null) {
            throw new LogicException(self::class.' has no reviewable yet; ResolveReviewable must run first.');
        }

        return $this->reviewable;
    }

    /**
     * The target narrowed to the capability the flow's guards need.
     *
     * Narrowing here rather than in a step of its own is what makes the
     * self-review guard total: EnsureNotSelfReview and NotifyReviewee both read
     * the target through this accessor, so a model on the enum that never
     * implemented App\Contracts\Reviewable stops the flow with
     * ReviewingNotSupported instead of skipping the guard the way the legacy
     * report request skipped its own for unlisted types.
     *
     * @throws LogicException When read before ResolveReviewable has run.
     * @throws ReviewingNotSupported When the enum names a model that cannot be reviewed.
     */
    public function reviewableAsSubject(): Reviewable
    {
        $reviewable = $this->reviewable();

        if (! $reviewable instanceof Reviewable) {
            throw ReviewingNotSupported::for($reviewable);
        }

        return $reviewable;
    }

    public function setReview(Review $review): void
    {
        $this->review = $review;
    }

    /**
     * @throws LogicException When read before PersistReview has run.
     */
    public function review(): Review
    {
        if ($this->review === null) {
            throw new LogicException(self::class.' has no review yet; PersistReview must run first.');
        }

        return $this->review;
    }
}
