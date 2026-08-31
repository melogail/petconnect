<?php

namespace App\Http\Controllers\Web;

use App\Actions\Reports\CreateReport;
use App\Enums\Reportable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Models\Report as ReportModel;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * User reports filed against any reportable model.
 *
 * One action, because reports have one user-facing operation. Triage lives in
 * Nova on the `admins` guard and is Phase 3's.
 *
 * A named controller method rather than the legacy `__invoke`: Wayfinder emits
 * one function per controller method, and a single-action controller gives the
 * frontend `ReportController()` with no verb in the name. `store` reads the
 * same as every other write in this application.
 *
 * `reports` is a named limiter defined in
 * AppServiceProvider::configureRateLimiters(). The legacy report route had no
 * throttle at all, which matters more here than on any other write in the app:
 * an unthrottled report endpoint is a way to bury a moderation queue under
 * noise, and every accepted report now also writes a notification row per
 * moderator.
 */
class ReportController extends Controller
{
    /**
     * File a report against a whitelisted target.
     *
     * The target is `{reportable_type}` bound to App\Enums\Reportable plus a
     * digits-only `{reportable_id}`, so an unrecognised type is a 404 at the
     * router. That is the fix for the legacy hole: StoreReportRequest validated
     * `reportable_type` as `['required', 'string']` in the request body, and
     * then ran its self-report and duplicate guards only when that string was
     * `Review::class` or `Comment::class` — so any other value skipped both and
     * was written to the morph column as sent.
     *
     * Nothing else about the target is decided here.
     * Pipelines\Reports\SubmitReport resolves it, refuses a self-report through
     * App\Contracts\Reportable (so the guard covers every whitelisted type, not
     * two), refuses a duplicate, writes the row, and tells the moderators.
     */
    public function store(
        StoreReportRequest $request,
        Reportable $reportable_type,
        int $reportable_id,
        CreateReport $createReport,
    ): RedirectResponse {
        $this->authorize('create', ReportModel::class);

        $createReport->handle(
            reporter: $request->user(),
            reportableType: $reportable_type,
            reportableId: $reportable_id,
            category: $request->category(),
            reason: $request->reason(),
            description: $request->description(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Report submitted.')]);

        return back();
    }
}
