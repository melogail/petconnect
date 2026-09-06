<?php

namespace App\Pipelines\Reviews;

/**
 * Shared passable for every flow that writes review content.
 *
 * Submitting and revising differ in almost everything — one resolves a target,
 * checks for a self-review and a duplicate, and notifies; the other only
 * rewrites two columns on a row that already exists — but both put the same
 * user-written comment through the same cleaning. Holding the rating and the
 * comment here is what lets Shared\SanitizeReviewComment be one step both flows
 * run, so a review cannot be edited around the filter it was published through.
 *
 * That is a lesson from the comment vertical rather than a new idea: the legacy
 * app listed the same two content pipelines separately in `createComment` and
 * `updateComment`, and they would have drifted the first time one list changed.
 * Comments solved it with Pipelines\Comments\CommentContentContext; this is the
 * same shape for reviews.
 *
 * It is deliberately a *review* context and not a reuse of the comment one. A
 * step in this domain must not be handed a comment flow's passable — see
 * .ai/rules/pipelines.md, "Flow-specific steps hint their own context" — and a
 * review carries a `rate` that no comment context has anywhere to put.
 *
 * `bannedWords` and `mask` arrive already resolved from config by the Action
 * that runs the flow, so no step reads config() and either flow can be driven
 * with an explicit list from a test or the console.
 */
abstract class ReviewContentContext
{
    /**
     * @param  list<string>  $bannedWords
     */
    public function __construct(
        public readonly int $rate,
        protected ?string $comment = null,
        public readonly array $bannedWords = [],
        public readonly string $mask = '****',
    ) {}

    /**
     * The review comment as the flow has it so far, or null for a rating with
     * no words.
     */
    public function comment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }
}
