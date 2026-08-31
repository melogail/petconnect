<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Pipelines\Reviews\ReviseReview\PersistReviewEdit;
use App\Pipelines\Reviews\ReviseReview\ReviseReviewContext;
use App\Pipelines\Reviews\Shared\SanitizeReviewComment;
use Illuminate\Pipeline\Pipeline;

/**
 * Apply an edit to a review.
 *
 * Two steps is a short pipeline, and .ai/rules/pipelines.md says to default to
 * inline work — but the first of those steps is Shared\SanitizeReviewComment,
 * and running it here is the whole point: it is the same class the submit flow
 * runs, so a review cannot be edited around the filter it was submitted
 * through. Mirrors Actions\Comments\UpdateComment exactly, for the same reason.
 *
 * Only `rate` and `comment` are writable. The author and the target are settled
 * at submit time, so an edit cannot move a review to another profile or change
 * whose review it is — and, because it cannot change the target, it can never
 * violate the unique index, which is why there is no duplicate handling on this
 * path.
 *
 * Like CreateReview, this is where the masked word list is resolved from
 * config, so the step reads none.
 */
class UpdateReview
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(Review $review, int $rate, ?string $comment = null): Review
    {
        /** @var list<string> $bannedWords */
        $bannedWords = config('bad-words.words', []);

        $context = new ReviseReviewContext(
            review: $review,
            rate: $rate,
            comment: $comment,
            bannedWords: $bannedWords,
            mask: (string) config('bad-words.mask', '****'),
        );

        return $this->pipeline
            ->send($context)
            ->through([
                SanitizeReviewComment::class,
                PersistReviewEdit::class,
            ])
            ->then(fn (ReviseReviewContext $completed): Review => $completed->review);
    }
}
