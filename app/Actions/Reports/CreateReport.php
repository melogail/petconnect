<?php

namespace App\Actions\Reports;

use App\Enums\Reportable;
use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Models\Report;
use App\Models\User;
use App\Pipelines\Reports\SubmitReport\EnsureNotAlreadyReported;
use App\Pipelines\Reports\SubmitReport\EnsureNotSelfReport;
use App\Pipelines\Reports\SubmitReport\NotifyModerators;
use App\Pipelines\Reports\SubmitReport\PersistReport;
use App\Pipelines\Reports\SubmitReport\ResolveReportable;
use App\Pipelines\Reports\SubmitReport\SubmitReportContext;
use Illuminate\Pipeline\Pipeline;

/**
 * File a report against a whitelisted target.
 *
 * A sequence — resolve the target, refuse a self-report, refuse a duplicate,
 * write the row, tell the moderators — so it runs as a pipeline over a typed
 * context. The legacy CreateReport was a single `Report::create()` fed an
 * unvalidated array, with the guards living in the Form Request that called it
 * and applying to only two of the reportable types.
 *
 * Order is load bearing. Resolution comes first because both guards are
 * questions about the resolved target, and both run before the insert so a
 * refused report costs nothing. NotifyModerators runs last, so the back office
 * is never told about a row that failed to write.
 *
 * `EnsureNotAlreadyReported` is not the duplicate guarantee — the unique index
 * on `reports` is, and PersistReport converts its refusal into the same error.
 *
 * This Action resolves no tunables from config, because the flow has none: the
 * category and reason are closed enums and the description ceiling belongs to
 * the Form Request that validated it.
 *
 * Throttling is a named limiter on the route (`throttle:reports`), not a step.
 * The legacy report route had no throttle of any kind, which mattered more here
 * than anywhere else: an unthrottled report endpoint is a way to bury a
 * moderation queue.
 */
class CreateReport
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(
        User $reporter,
        Reportable $reportableType,
        int $reportableId,
        ReportCategory $category,
        ReportReason $reason,
        ?string $description = null,
    ): Report {
        $context = new SubmitReportContext(
            reporter: $reporter,
            reportableType: $reportableType,
            reportableId: $reportableId,
            category: $category,
            reason: $reason,
            description: $description,
        );

        return $this->pipeline
            ->send($context)
            ->through([
                ResolveReportable::class,
                EnsureNotSelfReport::class,
                EnsureNotAlreadyReported::class,
                PersistReport::class,
                NotifyModerators::class,
            ])
            ->then(fn (SubmitReportContext $completed): Report => $completed->report());
    }
}
