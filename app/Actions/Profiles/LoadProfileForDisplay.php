<?php

namespace App\Actions\Profiles;

use App\Models\Pet;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Everything the public profile page renders, in three queries plus paging.
 *
 * The page is three things — the person, their listings and their reputation —
 * and each is a separate paginator or model rather than one nested eager load,
 * because two of them page independently.
 *
 * ## The rating summary is a subquery, not a loaded collection
 *
 * `HasReviews::withReviewStats()` adds `reviews_count` and `reviews_avg_rate`
 * as `withCount()` / `withAvg()` subqueries on the query already being issued,
 * so the header's "4.6 from 23 reviews" costs no extra round trip and no loaded
 * rows. **This is that scope's consumer** — it was written for this page and
 * had no caller until now, which is why it is named here rather than left to be
 * rediscovered. Anything else that needs an average rating should call it too
 * rather than loading `reviews` and averaging in PHP.
 *
 * ## Eager loads, and what breaks without them
 *
 * - `media` on the user, because ProfileResource reads the avatar with
 *   getFirstMediaUrl(). A single model fetched with `find()` has
 *   `preventLazyLoading` **off** (Builder::hydrate only arms it above one row —
 *   see .ai/rules/app.md), so forgetting this would be a silent extra query per
 *   render rather than an exception. It is the loader's job to be right here,
 *   not the guardrail's.
 * - `media` on the listings, because PetCardResource renders the cover photo
 *   with featuredPhotoUrl(); plus `category.media` for the category badge's
 *   own image and `breed` for its name. All three are on a payload that *is*
 *   iterated, so the guardrail does catch a miss here — it caught this one.
 *
 * `PetCardResource` reads `likes_count`, `comments_count` and `is_liked` with
 * `??`, so the counts are added as subqueries here; a loader that dropped them
 * would ship zeros rather than an N+1, which is why they are listed explicitly.
 *
 * ## The viewer's own relationship to the profile is two subqueries, not two queries
 *
 * `withLikedBy()` and `withReviewedBy()` add `is_liked` and `has_reviewed` to
 * the same single row this method was already fetching, so the header can draw
 * a filled heart and the review panel can say "you have already reviewed this
 * person" without either fact costing a round trip. `has_reviewed` is the newer
 * of the two and closes a real gap: the unique index on `reviews` and
 * SubmitReview\EnsureNotAlreadyReviewed have always refused a second review,
 * but nothing on the read side said so, so the form was offered to everybody
 * and the refusal arrived as a validation error after the user had written
 * one. Both are null for a guest and both are read with `??` in
 * Http\Resources\Profile\ProfileResource.
 *
 * The listings query does **not** eager load `user`: every card on this page
 * has the same owner — the profile — and `PetCardResource` emits it through
 * `whenLoaded('user')`, so the key is simply absent and the page saves a join
 * it would only use to repeat one name. A profile page is the one place that is
 * true.
 *
 * ## Visibility
 *
 * `Pet` soft deletes, so the global scope already hides retired listings, and
 * the query filters to the ones the profile is advertising. Whether the profile
 * itself may be read at all is UserPolicy::view's, called from the controller —
 * a deactivated account's page is hidden from **everyone**, its owner included.
 * There is no owner carve-out and there deliberately cannot be one: an account
 * that is not active is logged out by Http\Middleware\EnsureAccountIsActive
 * before it can be the viewer. Read UserPolicy's docblock before assuming
 * otherwise; it is written against exactly this misreading.
 *
 * The legacy ProfileController::show did `$user->load(['pets', 'reviews' => ...])`
 * with no bound of any kind on either, so a profile with 400 listings shipped
 * all 400 in the Inertia payload.
 */
class LoadProfileForDisplay
{
    /**
     * @return array{
     *     user: User,
     *     listings: LengthAwarePaginator<int, Pet>,
     *     reviews: LengthAwarePaginator<int, Review>
     * }
     */
    public function handle(User $profile, ?User $viewer = null): array
    {
        return [
            'user' => $this->summary($profile, $viewer),
            'listings' => $this->listings($profile, $viewer),
            'reviews' => $this->reviews($profile, $viewer),
        ];
    }

    /**
     * The profile itself, with its avatar and its rating summary.
     */
    protected function summary(User $profile, ?User $viewer): User
    {
        /** @var User $user */
        $user = User::query()
            ->withReviewStats()
            ->withCount('pets')
            ->withLikedBy($viewer)
            ->withReviewedBy($viewer)
            ->with('media')
            ->findOrFail($profile->getKey());

        return $user;
    }

    /**
     * A page of the profile's listings, newest first.
     *
     * @return LengthAwarePaginator<int, Pet>
     */
    protected function listings(User $profile, ?User $viewer): LengthAwarePaginator
    {
        return $profile->pets()
            ->with(['media', 'category.media', 'breed'])
            ->withCount(['likes', 'comments'])
            ->withLikedBy($viewer)
            ->latest('created_at')
            ->latest('id')
            ->paginate($this->listingsPerPage(), pageName: 'listings');
    }

    /**
     * A page of the reviews written about the profile, newest first.
     *
     * Read through the model's own `reviews()` relation, so no morph value is
     * built by hand — `reviewable_type` holds the alias `user` and a
     * class-name comparison would match nothing and say nothing. Same
     * arrangement as Actions\Reviews\ListReviews, and `reviews.index` pages the
     * rest of this list from the same relation with the same ordering.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    protected function reviews(User $profile, ?User $viewer): LengthAwarePaginator
    {
        return $profile->reviews()
            ->with('user.media')
            ->withReportedBy($viewer)
            ->latest('created_at')
            ->latest('id')
            ->paginate($this->reviewsPerPage(), pageName: 'reviews');
    }

    protected function listingsPerPage(): int
    {
        return (int) config('petconnect.profiles.listings_per_page', 12);
    }

    protected function reviewsPerPage(): int
    {
        return (int) config('petconnect.profiles.reviews_per_page', 10);
    }
}
