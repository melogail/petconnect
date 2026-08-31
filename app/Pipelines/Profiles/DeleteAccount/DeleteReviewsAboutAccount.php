<?php

namespace App\Pipelines\Profiles\DeleteAccount;

use App\Models\Review;
use Closure;

/**
 * Remove the review rows themselves — both directions.
 *
 * Runs after DeleteContentReports, so a report never outlives the review it was
 * filed against.
 *
 * The reviews the account *wrote* would cascade off `reviews.user_id` anyway;
 * the reviews written **about** the account would not, because
 * `reviews.reviewable_id` is a morph column and carries no foreign key. That
 * second set is the one nothing else in the application removes:
 * Actions\Reviews\DeleteReview is handed a single review by its own controller
 * and never hears about an account closing, and Review::resolveRouteBinding()
 * only hides the orphans from routes — it does not delete them, and
 * `withReviewStats()` on any other query would keep averaging them.
 *
 * Both sets are deleted together for the same reason DeleteContentComments
 * takes the whole collected set: one DELETE, no dependence on which half the
 * cascade would have covered.
 *
 * Reviews do not soft delete and `Review` has no observer, so a bulk delete
 * skips nothing.
 */
class DeleteReviewsAboutAccount
{
    public function handle(DeleteAccountContext $context, Closure $next): mixed
    {
        Review::query()
            ->whereKey($context->reviewIds())
            ->delete();

        return $next($context);
    }
}
