<?php

namespace App\Pipelines\Reviews\ReviseReview;

use Closure;

/**
 * Write the edited rating and comment.
 *
 * Two columns, named explicitly rather than forwarded from a request bag: an
 * edit cannot move a review to another profile or reassign its author, and
 * naming the columns here is what makes that true regardless of what a future
 * caller passes to the Action.
 *
 * `comment` is written even when null, because a review PUT is a replacement of
 * the two writable columns rather than a patch — "I deleted what I wrote" has
 * to be expressible. That is why UpdateReviewRequest puts `present` on
 * `comment`: an omitted key would otherwise be indistinguishable from a
 * cleared one and would wipe the text silently. See
 * App\Concerns\ReviewValidationRules.
 *
 * No transaction: one UPDATE is already atomic.
 */
class PersistReviewEdit
{
    public function handle(ReviseReviewContext $context, Closure $next): mixed
    {
        $context->review->update([
            'rate' => $context->rate,
            'comment' => $context->comment(),
        ]);

        return $next($context);
    }
}
