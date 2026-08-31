<?php

use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\Report;

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
