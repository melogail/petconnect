<?php

namespace App\Pipelines\Reviews\Shared;

use App\Actions\Content\SanitizeContent;
use App\Pipelines\Reviews\ReviewContentContext;
use Closure;

/**
 * Trim the review's comment and mask the words the app will not publish.
 *
 * The work lives in App\Actions\Content\SanitizeContent, which is deliberately
 * domain-free and already shared with the comment vertical — a review is public
 * text written about a named person, so it is the last place that should have
 * been left out of the filter. The legacy app ran its bad-word pipelines on
 * comments only; a review's `comment` went into the column exactly as typed.
 *
 * This step lives in Shared/ rather than in SubmitReview/ because both review
 * flows run it: a review that could be edited around the filter it was
 * submitted through would make the filter decorative. It is the same exemption
 * Pipelines\Comments\Shared\CleanContent and Pipelines\Pets\Shared\* have, and
 * the same one .ai/rules/pipelines.md carves out — a Shared step hints the
 * abstract context, a flow step hints its own.
 *
 * It hints App\Pipelines\Reviews\ReviewContentContext and never the comment
 * domain's context: widening a step to a flow it was not written for is exactly
 * what the flow-specific hint rule exists to prevent, and a review carries a
 * `rate` that has no home on a comment passable.
 *
 * A null comment — a rating with no words, which is a legitimate review — is
 * passed through untouched rather than being turned into an empty string, so
 * the column stays null and the resource keeps emitting null.
 */
class SanitizeReviewComment
{
    public function __construct(private readonly SanitizeContent $sanitizeContent) {}

    public function handle(ReviewContentContext $context, Closure $next): mixed
    {
        $comment = $context->comment();

        if ($comment !== null) {
            $cleaned = $this->sanitizeContent->handle(
                content: $comment,
                bannedWords: $context->bannedWords,
                mask: $context->mask,
            );

            $context->setComment($cleaned === '' ? null : $cleaned);
        }

        return $next($context);
    }
}
