<?php

namespace App\Actions\Reviews;

use App\Contracts\Reviewable as ReviewableContract;
use App\Enums\Reviewable;
use App\Exceptions\Reviews\ReviewingNotSupported;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * A page of a reviewable's reviews, newest first.
 *
 * The read half of the vertical, and the same shape as
 * Actions\Comments\ListCommentThread: a page that shows reviews ships a bounded
 * first slice inside its own payload, and this is how the rest is paged in
 * without a visit.
 *
 * ## The target is resolved, never filtered for
 *
 * Resolution goes through the Reviewable enum's class map and
 * findVisibleOrFail(), so a class name from the URL never reaches Eloquent and
 * the target's own visibility rules apply — the read path gets the identical
 * treatment the write path gets in SubmitReview\ResolveReviewable, from the
 * same method.
 *
 * The page is then read off the resolved model's own `reviews()` relation, so
 * this Action builds no morph filter of its own. That is not tidiness: a morph
 * map is enforced here, so `reviewable_type` holds the alias `user`, and a
 * hand-written `where('reviewable_type', User::class)` would match zero rows
 * and say nothing about it. Reading through the relation makes that class of
 * bug unwritable. See .ai/rules/app.md.
 *
 * Narrowing to App\Contracts\Reviewable first is what makes the relation
 * available and states the same invariant the write path states: an enum case
 * for a model that never opted in is a code bug, not a 404.
 *
 * ## Ordering and eager loading
 *
 * Newest first with an explicit `id` tiebreak, the house convention for
 * thread-shaped lists (see Actions\Messaging\PaginateConversationMessages).
 * Several reviews can share a `created_at` to the second — a seeder, a test,
 * two quick submissions — and a sort with ties is not a stable paginator: the
 * same row can appear on two pages or on none.
 *
 * `user.media` is eager loaded because ReviewAuthorResource reads the avatar
 * with getFirstMediaUrl(). Measured on 10 reviews, the three ways to get that
 * wrong behave differently and only one of them is loud:
 *
 * - `with('user.media')` — 4 queries here, 5 including the target resolution.
 * - `with('user')` alone — LazyLoadingViolationException on `media`, because
 *   `media-library.force_lazy_loading` is inverted outside production
 *   precisely so the guardrail sees this (see .ai/rules/config.md).
 * - no eager load at all — **2 queries, no exception, and no `author` key in
 *   the payload**. ReviewResource emits the author through `whenLoaded('user')`,
 *   so an unloaded relation is a dropped key rather than a lazy load, and the
 *   response is 25% smaller instead of 10 queries heavier. Nothing in the
 *   framework catches that; the eager load here is the only thing that does,
 *   and a test protecting it has to assert the key rather than the count.
 *
 * `has_reported` is a withExists() subquery on the query already being issued,
 * so it adds a column rather than a round trip: measured at 2, 10 and 25
 * reviews on one page, this Action is a flat 5 queries.
 *
 * Every query figure above is Action-scoped and measured under phpunit.xml's
 * `SESSION_DRIVER=array`; a real request pays 2-3 more for the `sessions` and
 * `cache` tables while `.env` keeps the `database` drivers. See
 * .ai/rules/app.md.
 *
 * A null viewer is a guest, and `has_reported` is simply absent for them;
 * ReviewResource defaults it to false.
 */
class ListReviews
{
    /**
     * @return LengthAwarePaginator<int, Review>
     *
     * @throws ModelNotFoundException<Model> When the target is gone or hidden.
     * @throws ReviewingNotSupported When the enum names a model that cannot be reviewed.
     */
    public function handle(
        Reviewable $reviewableType,
        int $reviewableId,
        ?User $viewer = null,
        ?int $perPage = null,
    ): LengthAwarePaginator {
        $reviewable = $reviewableType->findVisibleOrFail($reviewableId);

        if (! $reviewable instanceof ReviewableContract) {
            throw ReviewingNotSupported::for($reviewable);
        }

        $perPage ??= (int) config('petconnect.reviews.per_page', 10);

        return $reviewable->reviews()
            ->with('user.media')
            ->withReportedBy($viewer)
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);
    }
}
