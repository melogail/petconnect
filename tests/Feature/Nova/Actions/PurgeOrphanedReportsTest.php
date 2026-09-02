<?php

use App\Models\Admin;
use App\Models\Report;
use App\Models\Review;
use Illuminate\Support\Facades\Event;

const PURGE_ORPHANS_ACTION = '/nova-api/reports/action?action=purge-orphaned-reports';

/**
 * The orphan this action exists to clear, built the way one actually appears.
 *
 * `reports.reportable_id` is a morph column, so it carries no foreign key and
 * nothing cascades it: deleting the review leaves the report in the moderation
 * queue with `reportable` resolving to null. `pending()` because the queue is
 * where the orphan is stuck — the factory's default status is random, so a
 * report awaiting a decision has to be asked for.
 */
function orphanedReport(): Report
{
    $review = Review::factory()->create();
    $report = Report::factory()->pending()->forReportable($review)->create();
    $review->delete();

    return $report;
}

/**
 * The narrow job that does need a delete: a report whose target has already
 * gone can neither be acted on nor dismissed, because every decision
 * ChangeReportStatus offers is a decision *about* something. ReportPolicy's
 * `delete` is false, so this action is the only remaining route to removing a
 * report and `runDestructiveAction` is what lets it past that refusal.
 *
 * Which makes this refusal the whole of what stands between an admin's "select
 * all" and the evidence `update: false` exists to protect. It refuses by name
 * rather than skipping, and if it stops naming the offending row — or stops
 * firing at all — the policy's refusal has been handed back with the other hand
 * and nothing else notices.
 *
 * Selected alongside a genuine orphan, because the run is refused as a whole:
 * a moderator who fixes their selection must find both rows where they left
 * them, not one of them already destroyed.
 */
test('refuses the whole selection when one report still points at a live target, naming it', function () {
    $admin = Admin::factory()->create();
    $targeted = Report::factory()->pending()->create();
    $orphaned = orphanedReport();

    $this->actingAs($admin, 'admin')
        ->postJson(PURGE_ORPHANS_ACTION, [
            'resources' => [$orphaned->getKey(), $targeted->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('danger', sprintf(
            'Nothing was deleted. Report #%d still points at a comment or review that exists, so it is evidence rather than an orphan. Open it and decide with Change Status instead.',
            $targeted->getKey(),
        ));

    $this->assertModelExists($targeted);
    $this->assertModelExists($orphaned);
});

/**
 * The other side of the guard: the orphan the whole action exists for is
 * actually cleared, and only the selection is touched. The survivor is a report
 * whose target is still there and which nobody selected — the row the guard
 * would have refused had it been included.
 */
test('purges a report whose target no longer exists', function () {
    $admin = Admin::factory()->create();
    $orphan = orphanedReport();
    $survivor = Report::factory()->pending()->create();

    $this->actingAs($admin, 'admin')
        ->postJson(PURGE_ORPHANS_ACTION, [
            'resources' => [$orphan->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('message', '1 orphaned report cleared.');

    $this->assertModelMissing($orphan);
    $this->assertModelExists($survivor);
});

/**
 * The guard is not the only way this can fail — a report can acquire a target
 * again between the check and the delete, and `reports` is read and written by
 * observers — so the delete needs the shape .ai/rules/nova-actions.md makes
 * non-negotiable. Without the transaction, a throw part way through leaves
 * some reports destroyed and some not, with no record of which; without the
 * `catch (Throwable)`, the admin gets a 500 they cannot act on.
 *
 * The listener throws on the *second* delete, so the first has genuinely
 * happened and been rolled back rather than never having been attempted —
 * `$remainingMidFlight` is what tells the two apart, and without it the
 * surviving-rows assertions below would pass on an action that had done
 * nothing at all.
 */
test('restores every report when one of the selection cannot be deleted', function () {
    $admin = Admin::factory()->create();
    $first = orphanedReport();
    $second = orphanedReport();
    $attempts = 0;
    $remainingMidFlight = null;

    Event::listen('eloquent.deleting: '.Report::class, function () use (&$attempts, &$remainingMidFlight): void {
        if (++$attempts === 1) {
            return;
        }

        $remainingMidFlight = Report::query()->count();

        throw new RuntimeException('The report could not be cleared.');
    });

    $this->actingAs($admin, 'admin')
        ->postJson(PURGE_ORPHANS_ACTION, [
            'resources' => [$first->getKey(), $second->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('danger', 'Nothing was deleted. One of the selected reports could not be cleared, so the whole selection was rolled back. The failure has been logged.');

    expect($remainingMidFlight)->toBe(1);
    $this->assertModelExists($first);
    $this->assertModelExists($second);
});
