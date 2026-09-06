<?php

namespace App\Http\Requests\Report;

use App\Concerns\ReportValidationRules;
use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new report.
 *
 * ## What this class deliberately does not do
 *
 * The legacy StoreReportRequest of the same name did four things this one does
 * not, and each of them is now somewhere better:
 *
 * 1. It validated `reportable_type` as `['required', 'string']` — an
 *    unrestricted class name in the request body. There is no such key here:
 *    the target is `POST reports/{reportable_type}/{reportable_id}` with
 *    `{reportable_type}` bound to App\Enums\Reportable, so an unknown type is a
 *    404 at the router and this request never sees it.
 * 2. It ran the self-report and duplicate guards in `withValidator()`, behind
 *    an early return that fired only for `Review::class` and `Comment::class`,
 *    so every other value skipped both. They are now
 *    Pipelines\Reports\SubmitReport\EnsureNotSelfReport and
 *    EnsureNotAlreadyReported, written against App\Contracts\Reportable so they
 *    cannot be type-specific.
 * 3. Its duplicate guard queried `where('reportable_type', $reportableType)`
 *    with a class name. That worked in the legacy app, which registered no
 *    morph map; ported as-is under this application's enforced map it would
 *    have matched zero rows and passed every duplicate. A Form Request cannot
 *    see the resolved target, so it has to rebuild the morph filter by hand —
 *    which is why morph existence checks stay out of Form Requests entirely.
 *    See .ai/rules/app.md.
 * 4. It implemented `authorize()` as `auth()->check()` while sitting behind
 *    `auth` middleware. Authorization is ReportPolicy::create, called with
 *    $this->authorize() in ReportController, per .ai/rules/controllers.md.
 *
 * `category` was not validated at all in legacy — CreateReport read it off an
 * unvalidated key and fell back to `ReportCategory::other` — so a typo became a
 * silently miscategorised report instead of a 422. Both enums are validated
 * here and handed to the Action as cases rather than strings.
 *
 * `status` is accepted under no name: it is a moderator decision and is outside
 * Report's #[Fillable]. See .ai/rules/models.md.
 */
class StoreReportRequest extends FormRequest
{
    use ReportValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->reportRules();
    }

    /**
     * The submitted category.
     */
    public function category(): ReportCategory
    {
        return ReportCategory::from((string) $this->validated('category'));
    }

    /**
     * The submitted reason.
     */
    public function reason(): ReportReason
    {
        return ReportReason::from((string) $this->validated('reason'));
    }

    /**
     * The reporter's own words, or null when they added none.
     */
    public function description(): ?string
    {
        $description = $this->validated('description');

        return $description === null ? null : (string) $description;
    }
}
