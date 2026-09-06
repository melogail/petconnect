<?php

use App\Models\Admin;
use App\Models\Report;
use App\Models\Review;

/**
 * The adapter onto App\Actions\Reviews\DeleteReview, which owns the deletion
 * and is covered by tests/Feature/Actions/Reviews/DeleteReviewTest.php. What is
 * proved here is the wiring and the reason Nova's built-in delete is off for
 * this resource: `reports.reportable_id` is a morph column with no foreign key,
 * so nothing in the database removes a review's reports when the review goes,
 * and a report with a null `reportable` is exactly the row the moderation queue
 * cannot resolve.
 */
test('deletes a review and the reports filed against it', function () {
    $admin = Admin::factory()->create();
    $review = Review::factory()->create();
    $report = Report::factory()->pending()->forReportable($review)->create();

    $bystander = Review::factory()->create();
    $bystanderReport = Report::factory()->pending()->forReportable($bystander)->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/reviews/action?action=delete-review-with-reports', [
            'resources' => [$review->getKey()],
        ])
        ->assertOk()
        ->assertJsonPath('message', '1 review(s) deleted, along with the reports filed against them.');

    $this->assertModelMissing($review);
    $this->assertModelMissing($report);

    $this->assertModelExists($bystander);
    $this->assertModelExists($bystanderReport);
});

test('deletes every selected review when several are run at once', function () {
    $admin = Admin::factory()->create();
    $reviews = Review::factory()->count(3)->create();
    $bystander = Review::factory()->create();

    $this->actingAs($admin, 'admin')
        ->postJson('/nova-api/reviews/action?action=delete-review-with-reports', [
            'resources' => $reviews->modelKeys(),
        ])
        ->assertOk()
        ->assertJsonPath('message', '3 review(s) deleted, along with the reports filed against them.');

    expect(Review::query()->pluck('id')->all())->toBe([$bystander->getKey()]);
});
