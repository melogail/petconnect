<?php

namespace App\Pipelines\Reports\SubmitReport;

use App\Contracts\Reportable;
use App\Enums\Reportable as ReportableType;
use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Exceptions\Reports\ReportingNotSupported;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Passable for the submit report flow.
 *
 * ## This class is where the second legacy hole is closed for good
 *
 * The legacy StoreReportRequest accepted `'reportable_type' => ['required',
 * 'string']` — an unrestricted string in the request body — and its
 * self-report and duplicate guards ran inside an early return that fired only
 * for `Review::class` and `Comment::class`. Every other value skipped both
 * guards and was written to the `reportable_type` column as sent.
 *
 * Here the target arrives as an App\Enums\Reportable case plus an integer id,
 * because `{reportable_type}` is bound to that enum at the router. There is no
 * `reportable_type` rule in the Form Request because there is no
 * `reportable_type` key: an unknown type is a 404 before any controller runs,
 * and this context has no field a class name could occupy.
 *
 * `category` and `reason` are enum cases rather than strings, resolved by the
 * Form Request. The legacy CreateReport read them off unvalidated input with
 * `tryFrom(...) ?? ::other`, so a bad value became a silent "Other" instead of
 * a 422.
 *
 * `status` is deliberately absent from this context and from the request: it is
 * a moderator decision, it is outside Report's #[Fillable], and the model
 * mirrors the column default in $attributes so a freshly created Report reads
 * back as Pending. See .ai/rules/models.md.
 */
class SubmitReportContext
{
    /**
     * The resolved target, once ResolveReportable has run.
     */
    protected ?Model $reportable = null;

    /**
     * The filed report, once PersistReport has run.
     */
    protected ?Report $report = null;

    public function __construct(
        public readonly User $reporter,
        public readonly ReportableType $reportableType,
        public readonly int $reportableId,
        public readonly ReportCategory $category,
        public readonly ReportReason $reason,
        public readonly ?string $description = null,
    ) {}

    public function setReportable(Model $reportable): void
    {
        $this->reportable = $reportable;
    }

    /**
     * @throws LogicException When read before ResolveReportable has run.
     */
    public function reportable(): Model
    {
        if ($this->reportable === null) {
            throw new LogicException(self::class.' has no reportable yet; ResolveReportable must run first.');
        }

        return $this->reportable;
    }

    /**
     * The target narrowed to the capability the flow's guards need.
     *
     * This accessor is the reason EnsureNotSelfReport applies to *every*
     * reportable type rather than to a hardcoded two. Both guards read the
     * target through it, so a case on App\Enums\Reportable that never
     * implemented App\Contracts\Reportable stops the flow with
     * ReportingNotSupported rather than being waved past the checks — which is
     * exactly what the legacy `in_array($reportableType, [Review::class,
     * Comment::class], true)` early return did for every unlisted value.
     *
     * @throws LogicException When read before ResolveReportable has run.
     * @throws ReportingNotSupported When the enum names a model that cannot be reported.
     */
    public function reportableAsTarget(): Reportable
    {
        $reportable = $this->reportable();

        if (! $reportable instanceof Reportable) {
            throw ReportingNotSupported::for($reportable);
        }

        return $reportable;
    }

    public function setReport(Report $report): void
    {
        $this->report = $report;
    }

    /**
     * @throws LogicException When read before PersistReport has run.
     */
    public function report(): Report
    {
        if ($this->report === null) {
            throw new LogicException(self::class.' has no report yet; PersistReport must run first.');
        }

        return $this->report;
    }
}
