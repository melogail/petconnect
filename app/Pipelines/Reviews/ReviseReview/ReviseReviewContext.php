<?php

namespace App\Pipelines\Reviews\ReviseReview;

use App\Models\Review;
use App\Pipelines\Reviews\ReviewContentContext;

/**
 * Passable for the revise review flow.
 *
 * Only `rate` and `comment` are writable. The author, the target and the
 * timestamp are settled at submit time, so an edit has no attribute bag to get
 * wrong: it cannot move a review to another profile, reassign its author, or
 * turn a review of somebody else into a review of the editor.
 *
 * The review itself is public and readonly here because the flow revises the
 * row it was handed — the controller took it from a route binding that already
 * vetted its visibility (Review::resolveRouteBinding) and a policy that already
 * vetted the editor.
 */
class ReviseReviewContext extends ReviewContentContext
{
    /**
     * @param  list<string>  $bannedWords
     */
    public function __construct(
        public readonly Review $review,
        int $rate,
        ?string $comment = null,
        array $bannedWords = [],
        string $mask = '****',
    ) {
        parent::__construct($rate, $comment, $bannedWords, $mask);
    }
}
