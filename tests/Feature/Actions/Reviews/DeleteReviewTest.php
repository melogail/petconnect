<?php

use App\Actions\Reviews\DeleteReview;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;

/**
 * Reports reach a review through a morph column, which can carry no foreign
 * key, so nothing in the database removes them when the review goes. Left
 * behind, they are moderation-queue items whose `reportable()` resolves to
 * null: a moderator can neither act on them nor dismiss them. See the rule
 * against letting a cascade be the only thing deleting a polymorphic child in
 * .ai/rules/pipelines.md.
 */
test('removes the review and the reports filed against it', function () {
    $review = Review::factory()->create();
    $report = Report::factory()->forReportable($review)->create();

    app(DeleteReview::class)->handle($review);

    $this->assertModelMissing($review);
    $this->assertModelMissing($report);
});

test('leaves the reports filed against another review in place', function () {
    $review = Review::factory()->create();
    $survivor = Review::factory()->create();
    $report = Report::factory()->forReportable($survivor)->create();

    app(DeleteReview::class)->handle($review);

    $this->assertModelExists($report);
});

/**
 * The unique index is on (user, target), so two reporters is the only way to
 * put two reports on one review.
 */
test('removes every report filed against the review, not just the first', function () {
    $review = Review::factory()->create();
    Report::factory()->for(User::factory()->create())->forReportable($review)->create();
    Report::factory()->for(User::factory()->create())->forReportable($review)->create();

    app(DeleteReview::class)->handle($review);

    $this->assertDatabaseEmpty('reports');
});
