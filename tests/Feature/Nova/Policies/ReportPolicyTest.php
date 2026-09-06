<?php

use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\Report;

/**
 * A report is evidence somebody filed: an admin reads it and decides on it,
 * they do not author it and they do not edit it. `create` and `update` are both
 * false and App\Nova\Report exposes no writable field at all.
 *
 * That would ordinarily close the moderation queue too, because Nova asks
 * `canRun`, then `runAction`, then `update` — an action on a resource whose
 * `update` is false is refused along with the form. `runAction` returning true
 * is what keeps the queue moderatable, and it is the single most load-bearing
 * line in the policy: without it the whole reporting feature has nowhere to be
 * acted on. Both halves are asserted together, because a change to either one
 * alone silently produces a back office that can read reports and never close
 * one.
 */
test('returns 403 to a report edited through Nova while the decision action still runs', function () {
    $admin = Admin::factory()->create();
    $report = Report::factory()->pending()->create();

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/reports/{$report->getKey()}", ['status' => ReportStatus::Resolved->value])
        ->assertForbidden();

    expect($report->fresh()->status)->toBe(ReportStatus::Pending);

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/reports/action?action=change-status', [
            'resources' => [$report->getKey()],
            'status' => ReportStatus::Resolved->value,
        ])
        ->assertOk();

    expect($report->fresh()->status)->toBe(ReportStatus::Resolved);
});

test('returns 403 to a request for the report edit form', function () {
    $admin = Admin::factory()->create();
    $report = Report::factory()->pending()->create();

    $this->actingAs($admin, 'admin')
        ->getJson("/nova-api/reports/{$report->getKey()}/update-fields")
        ->assertForbidden();
});

test('returns 403 to a report filed through Nova', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->getJson('/nova-api/reports/creation-fields')
        ->assertForbidden();
});

/**
 * `delete` is false, and Nova draws the built-in delete on the detail page, in
 * the row menu and in the index's bulk bar — where "select all" plus delete
 * empties the whole moderation queue in two clicks with no undo. That is
 * exactly the evidence `update: false` above exists to preserve, so the
 * built-in route must remove nothing at all.
 *
 * The one job that does need a delete — clearing a report whose target has
 * already gone — goes through PurgeOrphanedReports and `runDestructiveAction`
 * instead. That action's own guard, happy path and rollback are pinned in
 * tests/Feature/Nova/Actions/PurgeOrphanedReportsTest.php.
 */
test('removes nothing when reports are deleted through the built-in delete', function () {
    $admin = Admin::factory()->create();
    $reports = Report::factory()->count(2)->pending()->create();

    $this->actingAs($admin, 'admin')
        ->deleteJson('/nova-api/reports', ['resources' => $reports->modelKeys()])
        ->assertOk();

    expect(Report::query()->pluck('id')->all())->toBe($reports->modelKeys());
});
