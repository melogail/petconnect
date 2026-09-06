<?php

use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\Report;

/**
 * How much moderation work is outstanding, right now.
 *
 * An outstanding-work figure, not a rate: a report filed eight months ago and
 * never triaged is exactly the one that must be counted. That is why it does
 * not go through `$this->count($request, ...)`, whose rangeless fallback is one
 * day (see TotalUsersTest for the mechanism) — the oldest report in the fixture
 * below is the one a ranged reading would drop, and the queue would show a
 * comforting 0 while somebody's report sat unanswered.
 *
 * The number also drives the sidebar badge on the Reports item, so a wrong
 * reading here is a queue that stops announcing itself.
 */
test('counts every report still waiting for a decision, however old', function () {
    $this->freezeTime();
    Report::factory()->pending()->create(['created_at' => now()->subMonths(8)]);
    Report::factory()->pending()->create(['created_at' => now()->subWeeks(3)]);
    Report::factory()->pending()->create(['created_at' => now()]);

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->getJson('/nova-api/dashboards/cards/moderation/metrics/pending-reports')
        ->assertOk()
        ->assertJsonPath('value.value', 3);
});

test('leaves out the reports that already carry a decision', function () {
    Report::factory()->pending()->create();

    foreach ([ReportStatus::Reviewed, ReportStatus::Resolved, ReportStatus::Rejected] as $decided) {
        Report::factory()->create(['status' => $decided]);
    }

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->getJson('/nova-api/dashboards/cards/moderation/metrics/pending-reports')
        ->assertOk()
        ->assertJsonPath('value.value', 1);
});

test('returns zero rather than no data for an empty queue', function () {
    $this->assertDatabaseEmpty('reports');

    $this->actingAs(Admin::factory()->create(), 'admin')
        ->getJson('/nova-api/dashboards/cards/moderation/metrics/pending-reports')
        ->assertOk()
        ->assertJsonPath('value.value', 0)
        ->assertJsonPath('value.zeroResult', true);
});

/**
 * Deciding a report has to move the card, because the resource asks Nova to
 * refresh it when an action runs — a stale reading is what would let a moderator
 * think the queue is longer than it is.
 */
test('falls as reports are decided', function () {
    $admin = Admin::factory()->create();
    $queue = Report::factory()->count(3)->pending()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/reports/action?action=change-status', [
            'resources' => [$queue->first()->getKey()],
            'status' => ReportStatus::Resolved->value,
        ])
        ->assertOk();

    $this->actingAs($admin, 'admin')
        ->getJson('/nova-api/dashboards/cards/moderation/metrics/pending-reports')
        ->assertOk()
        ->assertJsonPath('value.value', 2);
});
