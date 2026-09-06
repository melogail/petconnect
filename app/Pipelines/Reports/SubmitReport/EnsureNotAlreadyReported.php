<?php

namespace App\Pipelines\Reports\SubmitReport;

use App\Exceptions\Reports\AlreadyReported;
use Closure;

/**
 * Refuse a second report of the same target by the same user.
 *
 * The friendly fast path, and explicitly not the guarantee — PersistReport's
 * catch on the unique index is. This was the *only* protection the legacy app
 * had, in StoreReportRequest::withValidator(), for two of its reportable types,
 * with no constraint on the table behind it: two concurrent submissions both
 * read "not reported yet" and both inserted.
 *
 * The check reads through the target's own `reports()` relation instead of
 * rebuilding a morph filter. A morph map is enforced here, so the column holds
 * the alias `comment`, and the legacy `where('reportable_type', $reportableType)`
 * — comparing against a fully qualified class name — would match zero rows and
 * report "not yet reported" every single time under this application's morph
 * configuration. (It worked in the legacy app, which registered no morph map
 * and stored class names; it is the port that would have broken it.) See
 * .ai/rules/app.md.
 *
 * @throws AlreadyReported
 */
class EnsureNotAlreadyReported
{
    public function handle(SubmitReportContext $context, Closure $next): mixed
    {
        $alreadyReported = $context->reportableAsTarget()
            ->reports()
            ->where('user_id', $context->reporter->getKey())
            ->exists();

        if ($alreadyReported) {
            throw AlreadyReported::make();
        }

        return $next($context);
    }
}
