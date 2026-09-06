<?php

use App\Models\Admin;
use App\Models\Report;
use App\Models\Review;

/**
 * The same shape as CommentPolicy and for the same reasons: a review is one
 * member's stated opinion of another, so an admin may read it and remove it but
 * never rewrite it.
 *
 * Nova's built-in delete is off because `reports.reportable_id` is a morph
 * column with no foreign key — removing a review this way leaves every report
 * filed against it sitting in the moderation queue with a target that resolves
 * to null, which is the one thing a moderator can neither act on nor dismiss.
 * App\Nova\Actions\DeleteReview removes both in one transaction.
 */
test('removes nothing when the built-in delete is aimed at a review', function () {
    $admin = Admin::factory()->create();
    $review = Review::factory()->create();
    $report = Report::factory()->forReportable($review)->create();

    $this->actingAs($admin, 'admin')
        ->deleteJson('/nova-api/reviews', ['resources' => [$review->getKey()]])
        ->assertOk();

    $this->assertModelExists($review);
    $this->assertModelExists($report);
});

test('reports a review as not deletable in the index payload', function () {
    $admin = Admin::factory()->create();
    Review::factory()->create();

    $this->actingAs($admin, 'admin')
        ->getJson('/nova-api/reviews')
        ->assertOk()
        ->assertJsonPath('resources.0.authorizedToDelete', false)
        ->assertJsonPath('resources.0.authorizedToUpdate', false);
});

test('returns 403 to a review edited through Nova', function () {
    $admin = Admin::factory()->create();
    $review = Review::factory()->rating(1)->create();

    $this->actingAs($admin, 'admin')
        ->putJson("/nova-api/reviews/{$review->getKey()}", ['rate' => 5])
        ->assertForbidden();

    expect($review->fresh()->rate)->toBe(1);
});
