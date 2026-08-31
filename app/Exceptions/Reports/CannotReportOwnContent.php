<?php

namespace App\Exceptions\Reports;

use Illuminate\Validation\ValidationException;

/**
 * The reporter is answerable for the content they are reporting.
 *
 * The legacy app had this check, in StoreReportRequest::withValidator(), but
 * behind an early return: it ran only when `reportable_type` was exactly
 * `Review::class` or `Comment::class`, while the rule for that key was
 * `['required', 'string']`. Any other string skipped the guard entirely and the
 * report was filed.
 *
 * Here the question is asked of the target itself
 * (App\Contracts\Reportable::reportSubjects()), and a target that cannot answer
 * it raises ReportingNotSupported instead of being waved through — so the check
 * covers every case on App\Enums\Reportable by construction rather than by a
 * list somebody has to remember to extend.
 *
 * ## The error key
 *
 * `report`, not `reportable_id`. The target is a URL segment now, not a
 * submitted field, so the legacy key would name an input the form no longer
 * has. See App\Exceptions\Reviews\CannotReviewSelf for the same reasoning and
 * for why ValidationException is the right base.
 */
class CannotReportOwnContent extends ValidationException
{
    public static function make(): self
    {
        return self::withMessages([
            'report' => __('You cannot report your own content.'),
        ]);
    }
}
