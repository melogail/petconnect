<?php

namespace App\Pipelines\Reviews\SubmitReview;

use App\Exceptions\Reviews\AlreadyReviewed;
use Closure;

/**
 * Refuse a second review of the same target by the same author.
 *
 * The friendly fast path, not the guarantee. `reviews` is unique on
 * (user_id, reviewable_type, reviewable_id) and PersistReview converts that
 * index's refusal into the same error, which is what actually closes the race:
 * two concurrent submissions both reach this step, both read "no review yet",
 * and only the index can decide between them. Keeping the pre-check as well is
 * worth one `exists()`, because it answers before an insert is attempted and
 * before the notification step is even in scope.
 *
 * The check reads through the target's own `reviews()` relation rather than
 * building a `where('reviewable_type', ...)` filter by hand. That is not
 * stylistic: a morph map is enforced in this application, so the column holds
 * the alias `user` and a hand-written comparison against a class name would
 * match zero rows and pass silently, every time. See .ai/rules/app.md.
 *
 * @throws AlreadyReviewed
 */
class EnsureNotAlreadyReviewed
{
    public function handle(SubmitReviewContext $context, Closure $next): mixed
    {
        $alreadyReviewed = $context->reviewableAsSubject()
            ->reviews()
            ->where('user_id', $context->author->getKey())
            ->exists();

        if ($alreadyReviewed) {
            throw AlreadyReviewed::make();
        }

        return $next($context);
    }
}
