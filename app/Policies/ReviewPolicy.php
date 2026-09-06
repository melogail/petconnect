<?php

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

/**
 * Authorization for reviews.
 *
 * Every review route runs through this policy via $this->authorize() in
 * ReviewController, including the public read: a guest-readable list of reviews
 * is a decision recorded here rather than the absence of a check.
 *
 * There is deliberately **no `view`**. It existed, returned an unconditional
 * true and had no call site anywhere — `reviews.index` asks `viewAny`, and
 * `reviews.update` / `reviews.destroy` ask `update` and `delete` — so it was
 * deleted rather than kept as decoration. A single review is never read back on
 * its own; if a route for that ever arrives, add the method with it. Reading a
 * list is `viewAny`'s and always was.
 *
 * ## What the legacy app had here
 *
 * `App\Policies\ReviewPolicy` was an empty class extending an abstract
 * `App\Policies\Policy`, which decided `update` and `delete` as
 * `isVerified() && $user->id === $model->user_id` — materially what is written
 * out longhand below — and `create` as `isVerified()`. But **nothing called
 * `create`**: ReviewController::store neither authorized nor validated, so any
 * authenticated session could file a review, of anyone, unverified, as often as
 * it liked. The rule existed; the call site did not. Spelling the rules out per
 * policy instead of inheriting them means reading this file tells you the whole
 * answer for reviews.
 *
 * The shared base also took an `Admin|User` first parameter and returned true
 * for any Admin on `update`, `delete`, `restore` and `forceDelete`, putting
 * moderation on the same gate as the web app's own authorization. That is not
 * ported. The methods here type hint User, so an Admin on the `admins` guard
 * cannot be authorised by them. Do not read the hint as the mechanism:
 * Gate::canBeCalledWithUser() short-circuits to true for any non-null user and
 * only reads the signature for guests, so an Admin reaching one of these raises
 * a TypeError rather than returning false. It is a tripwire; the guard is the
 * gate. Nova authorization belongs on the Nova resource.
 *
 * ## Two methods are asked once per rendered row, and must stay query-free
 *
 * Http\Resources\Review\ReviewResource emits `can_edit` / `can_delete` by
 * calling `$viewer->can('update'|'delete', $review)` for every review on a
 * page. `update` and `delete` below therefore decide from attributes already on
 * the model — `user_id` — and load no relation. A check that reached for the
 * review's target, or for a moderation role, would be one query per rendered
 * row and nothing would catch it: Gate calls are invisible to
 * preventLazyLoading reasoning and the resource looks free. If such a check is
 * ever needed, decide it once for the whole page instead. See
 * .ai/rules/policies.md.
 *
 * ## What is decided elsewhere, on purpose
 *
 * This policy only ever decides about the acting user. Whether the *target* may
 * be reviewed at all — that it exists, that it is visible, that it is not the
 * reviewer themselves, that they have not already reviewed it — depends on a
 * model resolved from the URL and is answered by Pipelines\Reviews\SubmitReview
 * (ModelNotFoundException, CannotReviewSelf, AlreadyReviewed, and the unique
 * index behind them). Whether a review whose target has vanished may be
 * addressed at all is Review::resolveRouteBinding()'s.
 */
class ReviewPolicy
{
    /**
     * Reading reviews is public: they are a profile's public reputation, and a
     * rating only signed-in visitors could see would be invisible on the page
     * it was written for.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Writing a review is public content about a named person, and it notifies
     * them, so it needs a verified account — the same bar CommentPolicy::create
     * sets for the same two reasons.
     */
    public function create(User $user): bool
    {
        return $user->isVerified();
    }

    /**
     * Query-free by contract: ReviewResource asks this once per rendered row.
     */
    public function update(User $user, Review $review): bool
    {
        return $user->isVerified() && $user->getKey() === $review->user_id;
    }

    /**
     * Only the author may delete their review.
     *
     * Deliberately not extended to the person being reviewed. Letting a subject
     * delete criticism of themselves would make the rating meaningless; their
     * escalation path is the report flow, and moderator-side removal is Phase
     * 3's to design on the Nova resource.
     *
     * Query-free by contract: ReviewResource asks this once per rendered row.
     */
    public function delete(User $user, Review $review): bool
    {
        return $user->isVerified() && $user->getKey() === $review->user_id;
    }
}
