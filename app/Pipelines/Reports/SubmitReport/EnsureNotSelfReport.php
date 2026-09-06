<?php

namespace App\Pipelines\Reports\SubmitReport;

use App\Exceptions\Reports\CannotReportOwnContent;
use App\Models\User;
use Closure;

/**
 * Refuse a report filed by someone answerable for the reported content.
 *
 * This is the guard the legacy app had but only half ran. In
 * StoreReportRequest::withValidator() it sat behind
 * `if (! in_array($reportableType, [Review::class, Comment::class], true)) {
 * return; }`, while the rule for that key was `['required', 'string']` — so any
 * other value reached Report::create() with neither this check nor the
 * duplicate check applied.
 *
 * It now applies to every type, and not because a longer list was written down:
 * the question is asked of the target itself through
 * App\Contracts\Reportable::reportSubjects(), read via the context's narrowing
 * accessor, so a case added to App\Enums\Reportable either implements the
 * contract and is checked here, or raises ReportingNotSupported. There is no
 * type branch left to forget.
 *
 * The legacy predicate was also duck-typed — `isset($reportable->user_id) &&
 * $reportable->user_id === auth()->id()` — which silently passed for any model
 * without that column and silently failed for any model whose `user_id` meant
 * something other than "author". Asking the model makes the answer the model's.
 *
 * Runs before the duplicate check and before any write, so a refused report
 * leaves nothing behind.
 *
 * @throws CannotReportOwnContent
 */
class EnsureNotSelfReport
{
    public function handle(SubmitReportContext $context, Closure $next): mixed
    {
        $isOwnContent = $context->reportableAsTarget()
            ->reportSubjects()
            ->contains(fn (User $subject): bool => $subject->is($context->reporter));

        if ($isOwnContent) {
            throw CannotReportOwnContent::make();
        }

        return $next($context);
    }
}
