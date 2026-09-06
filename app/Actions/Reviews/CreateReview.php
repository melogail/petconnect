<?php

namespace App\Actions\Reviews;

use App\Enums\Reviewable;
use App\Models\Review;
use App\Models\User;
use App\Pipelines\Reviews\Shared\SanitizeReviewComment;
use App\Pipelines\Reviews\SubmitReview\EnsureNotAlreadyReviewed;
use App\Pipelines\Reviews\SubmitReview\EnsureNotSelfReview;
use App\Pipelines\Reviews\SubmitReview\NotifyReviewee;
use App\Pipelines\Reviews\SubmitReview\PersistReview;
use App\Pipelines\Reviews\SubmitReview\ResolveReviewable;
use App\Pipelines\Reviews\SubmitReview\SubmitReviewContext;
use Illuminate\Pipeline\Pipeline;

/**
 * Submit a review of a whitelisted target.
 *
 * A sequence — resolve the target, refuse a self-review, refuse a duplicate,
 * clean the text, write the row, notify — so it runs as a pipeline over a typed
 * context rather than as one long method. The legacy equivalent was a
 * three-line controller and a five-line `CreateReviewAction::make()` that did
 * none of the first three.
 *
 * Order is load bearing throughout. Resolution comes first because every step
 * after it is a question about the resolved target. Both guards run before the
 * text is cleaned and before anything is written, so a refused submission costs
 * no insert and leaves nothing behind. Notification runs last, so nobody is
 * told about a row that failed to write.
 *
 * `EnsureNotAlreadyReviewed` is not the duplicate guarantee — the unique index
 * on `reviews` is, and PersistReview converts its refusal into the same error.
 * The step stays because answering before the insert is worth one `exists()`.
 *
 * This Action is where the flow's tunables are resolved — the masked word list
 * and the mask — so no step reads config() and the whole flow can be driven
 * with an explicit list from a test or the console.
 *
 * Throttling is deliberately not a step: it is a named limiter on the route
 * (`throttle:reviews`, defined in AppServiceProvider). A rate limit's only
 * meaningful outcome is a 429 with Retry-After, which is transport, and
 * .ai/rules/pipelines.md keeps steps HTTP-free.
 */
class CreateReview
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(
        User $author,
        Reviewable $reviewableType,
        int $reviewableId,
        int $rate,
        ?string $comment = null,
    ): Review {
        $context = new SubmitReviewContext(
            author: $author,
            reviewableType: $reviewableType,
            reviewableId: $reviewableId,
            rate: $rate,
            comment: $comment,
            bannedWords: $this->bannedWords(),
            mask: (string) config('bad-words.mask', '****'),
        );

        return $this->pipeline
            ->send($context)
            ->through([
                ResolveReviewable::class,
                EnsureNotSelfReview::class,
                EnsureNotAlreadyReviewed::class,
                SanitizeReviewComment::class,
                PersistReview::class,
                NotifyReviewee::class,
            ])
            ->then(fn (SubmitReviewContext $completed): Review => $completed->review());
    }

    /**
     * @return list<string>
     */
    protected function bannedWords(): array
    {
        /** @var list<string> $words */
        $words = config('bad-words.words', []);

        return $words;
    }
}
