<?php

namespace App\Concerns;

use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The rules the report Form Request accepts, and the single place each report
 * key is spelled.
 *
 * ## What is deliberately absent: `reportable_type` and `reportable_id`
 *
 * This is the second half of the fix for the legacy report hole. The legacy
 * StoreReportRequest validated `'reportable_type' => ['required', 'string']` —
 * an unrestricted string, with no `Rule::in` — and then ran its self-report and
 * duplicate guards only when that string was `Review::class` or
 * `Comment::class`, so every other value skipped both checks and still reached
 * `Report::create()`.
 *
 * There are no rules for those two keys here because they are no longer keys.
 * The target is `POST reports/{reportable_type}/{reportable_id}`, with
 * `{reportable_type}` bound to App\Enums\Reportable, so an unknown type is a
 * router 404 and this request never sees it. A whitelist that is not on the
 * request cannot be widened without the router noticing.
 *
 * A `Rule::in` here would be strictly worse than the router binding even if it
 * listed the right values: it would 422 rather than 404, it would have to be
 * kept in step with the enum by hand, and — because a morph map is enforced in
 * this app — any `Rule::exists()` written alongside it comparing
 * `reportable_type` to a class name would match zero rows and validate nothing.
 * See .ai/rules/app.md on keeping morph existence checks out of Form Requests.
 *
 * ## `category` and `reason` are closed enums
 *
 * Both were `['required', 'string']` in legacy (`category` was not validated at
 * all — CreateReport read it off an unvalidated key and fell back to
 * `ReportCategory::other`), so a report could be filed under a category the
 * moderation screen has no column for. `Rule::enum()` makes the cast on the
 * model total.
 *
 * `status` is not accepted from the request under any name: it is a moderator
 * decision, it is outside Report's #[Fillable], and .ai/rules/models.md is
 * where that is recorded.
 */
trait ReportValidationRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reportRules(): array
    {
        return [
            'category' => ['required', Rule::enum(ReportCategory::class)],
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'description' => ['nullable', 'string', 'max:'.$this->maxDescriptionLength()],
        ];
    }

    /**
     * The longest report description the application accepts, in characters.
     */
    public function maxDescriptionLength(): int
    {
        return (int) config('petconnect.reports.max_description_length', 1000);
    }
}
