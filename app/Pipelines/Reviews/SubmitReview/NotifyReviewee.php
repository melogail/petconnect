<?php

namespace App\Pipelines\Reviews\SubmitReview;

use App\Models\User;
use App\Notifications\ModelReviewedNotification;
use Closure;

/**
 * Tell the people a review was written about that it exists.
 *
 * Who that is comes from the target, through
 * App\Contracts\Reviewable::reviewSubjects() — the same method
 * EnsureNotSelfReview asks, which is why the two can never disagree about who
 * "the reviewee" is. For a User that is the user themselves; for a future
 * owned reviewable it would be the owner.
 *
 * The author is filtered out even though EnsureNotSelfReview has already made
 * that impossible, mirroring NotifyCommentable and LikeObserver: the filter is
 * cheap, and a flow that grows a second entry point should not depend on an
 * earlier step for a notification-side invariant.
 *
 * Runs last, after PersistReview, so nobody is told about a row that failed to
 * write. The legacy app sent nothing here at all — a user learned they had been
 * rated in public only by visiting their own profile.
 */
class NotifyReviewee
{
    public function handle(SubmitReviewContext $context, Closure $next): mixed
    {
        $review = $context->review();

        $context->reviewableAsSubject()
            ->reviewSubjects()
            ->filter(fn (User $subject): bool => ! $subject->is($context->author))
            ->unique(fn (User $subject): mixed => $subject->getKey())
            ->each(fn (User $subject) => $subject->notify(new ModelReviewedNotification($review)));

        return $next($context);
    }
}
