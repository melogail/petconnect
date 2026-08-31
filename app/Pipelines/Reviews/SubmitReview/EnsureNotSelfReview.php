<?php

namespace App\Pipelines\Reviews\SubmitReview;

use App\Exceptions\Reviews\CannotReviewSelf;
use App\Models\User;
use Closure;

/**
 * Refuse a review written by one of the people it would be about.
 *
 * The legacy app had no equivalent anywhere — no policy method, no request
 * rule, no guard in CreateReviewAction — so an account could rate itself, and
 * with no unique index on `reviews` it could do so repeatedly and set its own
 * public average.
 *
 * Who the review is "about" is the target's own answer, through
 * App\Contracts\Reviewable::reviewSubjects(), read via the context's narrowing
 * accessor. That is what makes the rule total: a case added to
 * App\Enums\Reviewable either implements the contract and is checked here, or
 * raises ReviewingNotSupported. There is no branch on type to forget to extend
 * — which is the exact mistake the legacy report request made next door.
 *
 * Runs before the duplicate check and before anything is written, so a refused
 * self-review leaves nothing behind and costs no insert.
 *
 * @throws CannotReviewSelf
 */
class EnsureNotSelfReview
{
    public function handle(SubmitReviewContext $context, Closure $next): mixed
    {
        $isSubject = $context->reviewableAsSubject()
            ->reviewSubjects()
            ->contains(fn (User $subject): bool => $subject->is($context->author));

        if ($isSubject) {
            throw CannotReviewSelf::make();
        }

        return $next($context);
    }
}
