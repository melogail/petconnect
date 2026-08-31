<?php

namespace App\Nova\Policies;

use App\Models\Admin;
use App\Nova\Report as ReportResource;

/**
 * Authorization for the Report resource, on the `admin` guard.
 *
 * A report is evidence somebody filed. An admin reads it and decides on it;
 * they do not author it and they do not edit it, so `create` and `update` are
 * both false and App\Nova\Report exposes no writable field at all.
 *
 * The decision itself is App\Nova\Actions\ChangeReportStatus, which assigns
 * `status` explicitly — the column is outside App\Models\Report's #[Fillable]
 * precisely so that only a moderator path can set it. Because `update` is
 * false, Nova would refuse that action too: its authorization order is canRun,
 * then runAction, then update. `runAction` below returns true and is what
 * makes moderation possible while the edit form stays closed.
 *
 * `delete` is false too, and the reason is the same one. There *is* a narrow
 * job that needs a delete — a report whose target has already gone resolves to
 * a null `reportable` and can neither be acted on nor dismissed — but
 * `authorizedToDelete` returning true on every row is not narrow at all. Nova
 * draws the delete control on the detail page, in the row menu and in the
 * index's bulk bar, where "select all" plus delete destroys the entire queue in
 * two clicks with no undo. That is precisely the evidence `update: false`
 * exists to protect, handed back by another route.
 *
 * App\Nova\Actions\PurgeOrphanedReports is the one way through instead, and it
 * refuses any selected report whose `reportable` still resolves.
 * `runDestructiveAction` below is what lets it past this `delete` refusal —
 * exactly the arrangement CategoryPolicy and DeleteCategory use.
 */
class ReportPolicy
{
    /**
     * Determine whether the admin can list reports.
     */
    public function viewAny(Admin $admin): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can view a report.
     */
    public function view(Admin $admin, ReportResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can file a report.
     *
     * No: reports come from members, through the application.
     */
    public function create(Admin $admin): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can edit a report.
     *
     * No: the reporter's own words and choices are the evidence.
     */
    public function update(Admin $admin, ReportResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin may run a non-destructive action.
     *
     * True, and load-bearing: without it ChangeReportStatus would fall through
     * to `update` above and be refused, leaving the queue unmoderatable.
     */
    public function runAction(Admin $admin, ReportResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin may run a destructive action.
     *
     * True, and load-bearing for the same reason runAction() is: without it
     * PurgeOrphanedReports would fall through to `delete` below and be refused,
     * leaving an orphaned report permanently stuck in the queue.
     */
    public function runDestructiveAction(Admin $admin, ReportResource $resource): bool
    {
        return true;
    }

    /**
     * Determine whether the admin can delete a report.
     *
     * No. Clearing an orphan goes through PurgeOrphanedReports, which checks
     * that it really is one; see the class docblock.
     */
    public function delete(Admin $admin, ReportResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can restore a report.
     *
     * `reports` does not soft delete.
     */
    public function restore(Admin $admin, ReportResource $resource): bool
    {
        return false;
    }

    /**
     * Determine whether the admin can permanently delete a report.
     */
    public function forceDelete(Admin $admin, ReportResource $resource): bool
    {
        return false;
    }
}
