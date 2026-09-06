<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * Delete a review and the reports filed against it.
 *
 * ## The reports are not optional cleanup
 *
 * `reports` reaches a review through a morph column, which can carry no foreign
 * key, so nothing in the database removes a review's reports when the review
 * goes. Left behind, they are rows in the moderation queue whose
 * `reportable()` resolves to null — an item a moderator cannot act on and
 * cannot dismiss, growing with every deleted review. That is the rule
 * .ai/rules/pipelines.md records against Comment ("never let a cascade be the
 * only thing deleting a row that has polymorphic children"), and the
 * relationship here has the same shape.
 *
 * ## This covers ONE of the two ways a review disappears, not both
 *
 * Scope statement, because an earlier version of this docblock read as though
 * the cleanup were complete. It runs on the review-author's own delete —
 * ReviewController::destroy — and on nothing else. `reviews.user_id` is
 * `cascadeOnDelete`, so deleting the *author's account* removes their reviews
 * at the database level, which fires no Eloquent event and never reaches this
 * class. Measured: A reviews B, C reports that review, A's account is deleted —
 * the review row is gone and C's report survives with `reportable` resolving to
 * null. `reviews.reviewable_id` is a morph column with no FK, so deleting B
 * instead strands the review itself and its reports with it.
 *
 * `comments.user_id` has the identical hole and has had it longer: deleting an
 * account cascades their comments (and, through `comments.parent_id`, every
 * descendant of those comments) without running Actions\Comments\DeleteComment,
 * so the likes and reports on all of them are stranded too.
 *
 * The account-deletion side is Actions\Profiles\DeleteUserAccount's, which
 * collects these rows before the cascade can run. Do not add a User branch
 * here: this Action is handed one review and knows nothing about who is being
 * deleted or why.
 *
 * That rule's further claim — that a stranded report later collides with a
 * genuine one on a recycled id — does not hold on this schema: `$table->id()`
 * emits `integer primary key autoincrement` on SQLite, verified on the live
 * `reviews` and `reports` tables, and AUTOINCREMENT is what stops ids being
 * reused. The dangling-queue-item reason above stands on its own and is why
 * this Action does the cleanup.
 *
 * Two deletes in one transaction, so a review can never be gone while its
 * reports survive. The transaction is opened here rather than inside a step
 * because it has to span both writes.
 *
 * ## Why this is not a pipeline
 *
 * Actions\Comments\DeleteComment is one because a comment subtree has to be
 * collected first and then has two kinds of polymorphic child to clear. A
 * review has no children of its own and exactly one polymorphic dependent, so
 * this is two statements — .ai/rules/pipelines.md says to default to inline
 * work and extract only when a second caller or standalone test value earns it,
 * and neither does here.
 *
 * Reviews do not soft delete, so this is permanent. Deliberate, and consistent
 * with Comment: a review's moderation trail is the report, and the reports are
 * being removed alongside it. The legacy destroy was a bare `$review->delete()`
 * with no transaction and no report cleanup at all.
 */
class DeleteReview
{
    public function handle(Review $review): bool
    {
        return DB::transaction(function () use ($review): bool {
            $review->reports()->delete();

            return (bool) $review->delete();
        });
    }
}
