<?php

use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\Report;
use Illuminate\Support\Facades\Event;

/**
 * The only writer of `reports.status` in the application.
 *
 * The column sits outside App\Models\Report's #[Fillable] on purpose — a
 * moderator decision is not something a request bag may carry — so the action
 * assigns the property directly rather than going near `update()` or `fill()`.
 * That the column refuses mass assignment is owned by
 * tests/Feature/Models/ReportTest.php, and that Nova offers no edit form for it
 * by tests/Feature/Nova/Policies/ReportPolicyTest.php; what is left, and what
 * is here, is that the one supported route works and moves exactly the reports
 * it was aimed at.
 */
test('moves a single report to the selected status', function () {
    $admin = Admin::factory()->create();
    $report = Report::factory()->pending()->create();
    $untouched = Report::factory()->pending()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/reports/action?action=change-status', [
            'resources' => [$report->getKey()],
            'status' => ReportStatus::Resolved->value,
        ])
        ->assertOk()
        ->assertJsonPath('message', '1 report(s) marked as Resolved.');

    $this->assertDatabaseHas('reports', ['id' => $report->getKey(), 'status' => 'resolved']);
    $this->assertDatabaseHas('reports', ['id' => $untouched->getKey(), 'status' => 'pending']);
});

test('moves every selected report when several are run at once', function () {
    $admin = Admin::factory()->create();
    $queue = Report::factory()->count(3)->pending()->create();
    $untouched = Report::factory()->pending()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/reports/action?action=change-status', [
            'resources' => $queue->modelKeys(),
            'status' => ReportStatus::Reviewed->value,
        ])
        ->assertOk()
        ->assertJsonPath('message', '3 report(s) marked as Reviewed.');

    expect(Report::query()->whereKey($queue->modelKeys())->pluck('status')->unique()->all())
        ->toBe([ReportStatus::Reviewed]);

    $this->assertDatabaseHas('reports', ['id' => $untouched->getKey(), 'status' => 'pending']);
});

/**
 * Every case of the enum is a status a moderator may choose, including moving a
 * decided report back to Pending — reopening is a decision too.
 */
test('accepts every case of the report status enum', function (ReportStatus $status) {
    $admin = Admin::factory()->create();
    $report = Report::factory()->create(['status' => ReportStatus::Reviewed]);

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/reports/action?action=change-status', [
            'resources' => [$report->getKey()],
            'status' => $status->value,
        ])
        ->assertOk();

    expect($report->fresh()->status)->toBe($status);
})->with(ReportStatus::cases());

test('returns 422 to a status that is not a case of the enum', function () {
    $admin = Admin::factory()->create();
    $report = Report::factory()->pending()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/reports/action?action=change-status', [
            'resources' => [$report->getKey()],
            'status' => 'closed-as-wontfix',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->assertDatabaseHas('reports', ['id' => $report->getKey(), 'status' => 'pending']);
});

test('returns 422 when no status is chosen', function () {
    $admin = Admin::factory()->create();
    $report = Report::factory()->pending()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/reports/action?action=change-status', [
            'resources' => [$report->getKey()],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status' => 'The Status field is required.']);

    $this->assertDatabaseHas('reports', ['id' => $report->getKey(), 'status' => 'pending']);
});

/**
 * A moderation queue that half moved is worse than one that did not move at
 * all: the statuses are the audit trail, and an admin who reads "something went
 * wrong" has no way to tell which of the fifty reports they selected now
 * carries a decision nobody made. The transaction is what keeps the selection
 * one unit of work and the `catch (Throwable)` is what turns the failure into
 * a sentence instead of a 500.
 *
 * The throw is injected on the second save, so the first report has genuinely
 * been moved and rolled back rather than never having been touched;
 * `$resolvedMidFlight` is what proves that, and without it the
 * still-pending assertions below would pass on a run that wrote nothing.
 */
test('leaves every selected report on its original status when one cannot be saved', function () {
    $admin = Admin::factory()->create();
    $first = Report::factory()->pending()->create();
    $second = Report::factory()->pending()->create();
    $attempts = 0;
    $resolvedMidFlight = null;

    Event::listen('eloquent.saving: '.Report::class, function () use (&$attempts, &$resolvedMidFlight): void {
        if (++$attempts === 1) {
            return;
        }

        $resolvedMidFlight = Report::query()->where('status', ReportStatus::Resolved)->count();

        throw new RuntimeException('The report could not be saved.');
    });

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/reports/action?action=change-status', [
            'resources' => [$first->getKey(), $second->getKey()],
            'status' => ReportStatus::Resolved->value,
        ])
        ->assertOk()
        ->assertJsonPath('danger', 'Nothing was changed. One of the selected reports could not be moved to the new status, so the whole selection was rolled back. The failure has been logged.');

    expect($resolvedMidFlight)->toBe(1);
    $this->assertDatabaseHas('reports', ['id' => $first->getKey(), 'status' => 'pending']);
    $this->assertDatabaseHas('reports', ['id' => $second->getKey(), 'status' => 'pending']);
});
