<?php

namespace App\Pipelines\Reports\SubmitReport;

use App\Models\Admin;
use App\Notifications\ReportFiledNotification;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Tell the back office that a report has been filed.
 *
 * ## Why this step exists at all
 *
 * In the legacy app a report was written and then vanished. There was no
 * notification, no digest, no email, and no Nova resource for `reports` — the
 * only way a moderator could learn that a user had reported abuse was to query
 * the table by hand. Everything upstream of this step is about making a report
 * correct; this step is about making it *arrive*.
 *
 * ## How a moderator is addressed, given the separate guard
 *
 * Moderators are App\Models\Admin records, authenticated on the `admins` guard,
 * and they are not App\Models\User — a User cannot be notified in their place
 * and an Admin cannot be reached through any of the app's user-facing
 * notification paths. Three things make the database channel work anyway:
 *
 * - `Admin` uses Illuminate\Notifications\Notifiable, so `notify()` is already
 *   available on it;
 * - `admin` is registered in AppServiceProvider::configureMorphMap(), so
 *   `notifications.notifiable_type` stores a stable alias rather than a class
 *   name;
 * - `notifications` is a single polymorphic table, so admin rows and user rows
 *   coexist without either side being able to read the other's.
 *
 * So a report lands as a `database` notification addressed to the `admin` morph
 * — the same channel and the same table every other notification in this app
 * uses, with no second mechanism and no cross-guard leak. Phase 3's Nova
 * `Report` resource is the screen that will triage them; this is the signal
 * that says one is waiting, and it does not depend on that resource existing.
 * Mail is deliberately not a channel here, matching every other notification in
 * the application: there is no queue worker configured yet.
 *
 * ## "Every admin" is today's definition of "the moderators"
 *
 * `admins` has no role or permission column, so there is no narrower group to
 * address. When roles land, this is one scope on Admin — `Admin::moderators()`
 * — and nothing else in the flow moves. Admins are read in chunks so the step
 * stays bounded no matter how large the table grows.
 *
 * An empty `admins` table means the report really does go nowhere, which is the
 * legacy behaviour this step exists to end, so it is logged rather than passed
 * over in silence.
 */
class NotifyModerators
{
    /**
     * How many moderators are loaded per round trip.
     */
    protected const CHUNK_SIZE = 100;

    public function handle(SubmitReportContext $context, Closure $next): mixed
    {
        $report = $context->report();
        $notified = 0;

        Admin::query()->chunkById(self::CHUNK_SIZE, function (Collection $admins) use ($report, &$notified): void {
            $admins->each(fn (Admin $admin) => $admin->notify(new ReportFiledNotification($report)));

            $notified += $admins->count();
        });

        if ($notified === 0) {
            Log::warning('A report was filed but no moderator could be notified.', [
                'report_id' => $report->getKey(),
                'reportable_type' => $report->reportable_type,
                'reportable_id' => $report->reportable_id,
            ]);
        }

        return $next($context);
    }
}
