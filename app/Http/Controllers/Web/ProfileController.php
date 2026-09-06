<?php

namespace App\Http\Controllers\Web;

use App\Actions\Likes\ToggleLike;
use App\Actions\Profiles\LoadProfileForDisplay;
use App\Concerns\CommentValidationRules;
use App\Concerns\ReviewValidationRules;
use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Resources\Pet\PetCardResource;
use App\Http\Resources\Profile\ProfileResource;
use App\Http\Resources\Review\ReviewResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public profile page.
 *
 * One method, one URI. Editing a profile is not here — it is
 * Settings\ProfileController, at `settings/profile`, and there is deliberately
 * no second edit screen at `profile/{user}/edit`: two routes writing the same
 * model is how the legacy app ended up with `profile.update` accepting both PUT
 * and POST at a URI that also served the read.
 *
 * ## Genuinely public, which the legacy route only looked
 *
 * The legacy declaration was
 * `Route::get('profile/{user}', ...)->name('profile.show')->withoutMiddleware('auth')`
 * **inside** a `Route::middleware(['auth', 'verified'])` group. Dropping `auth`
 * while keeping `verified` means EnsureEmailIsVerified still runs, finds no
 * user, and redirects to `verification.notice` — so the one route explicitly
 * marked public was unreachable to the public, and every shared profile link
 * bounced a signed-out visitor to a verification screen. Here the route sits
 * outside the group entirely and the decision is recorded in UserPolicy::view,
 * per .ai/rules/controllers.md ("a guest-visible page should be a recorded
 * decision in a policy, not the absence of a check").
 *
 * That policy is also where deactivation is enforced for reads: a deactivated
 * account's page is a 403 for everybody, the account included — it cannot hold
 * a session at all, because EnsureAccountIsActive ends it first.
 *
 * ## This is where the report vocabulary finally reaches the frontend
 *
 * ReportCategory::options() and ReportReason::options() had no route anywhere in
 * the application, so the report dialog the report vertical was built for had no
 * source for its two select controls. They ship as props here — the same
 * arrangement PetController::create uses for the listing form's enums — because
 * this page hosts the review report dialog. The legacy `profile/Show` shipped
 * `reportReasons` for exactly this reason and shipped no categories, which is
 * why its dialog could not fill its own category field.
 *
 * The pet detail page hosts the *comment* report dialog and needs the same two
 * props; that is a one-line addition to PetController::show and is flagged
 * rather than made here, because PetController belongs to the listings vertical.
 *
 * ## Why a validation Concern is used by a controller
 *
 * `reviewBounds` ships `petconnect.reviews.min_rate` / `max_rate` /
 * `max_comment_length` to the page, because the star widget cannot draw a scale
 * it has not been told the length of and had been hardcoding five. The config
 * comment promises that changing `max_rate` moves the validator and the
 * frontend together, and until this prop existed it could not: the two lived on
 * different sides of the wire with only the config file in common.
 *
 * Reading the bounds through App\Concerns\ReviewValidationRules rather than
 * from `config()` here is what makes the promise literal. The same accessors
 * that build the `min:`/`max:` rules build the prop, so there is one spelling
 * of each key and one default, and a bound cannot be changed for the validator
 * without changing it for the widget. HomeController does the same thing
 * through its Form Request's accessors for `filterBounds`; this page has no
 * Form Request to hang them on, so the Concern is used directly.
 *
 * `commentBounds` is here for the same reason and by the same route, through
 * App\Concerns\CommentValidationRules. The listings this page renders are the
 * same cards the feed renders, and a card opens the same comments dialog; a
 * composer that has not been told the ceiling ships no `maxlength` and no
 * counter, accepts more than the `max:` rule allows, and strands the text on
 * the 422. Only `pets.show` used to supply it, so the dialog behaved one way
 * from a listing page and another way from here. Same key and same snake_case
 * shape on all three pages, so the dialog reads one contract and never asks
 * which page mounted it. Both accessors are `config()` reads — no query.
 */
class ProfileController extends Controller
{
    use CommentValidationRules;
    use ReviewValidationRules;

    /**
     * Show a user's public profile.
     *
     * `{user}` binds by id. **Not by username** — User::getRouteKeyName() stays
     * `id` on purpose, because App\Enums\Reviewable and Reportable resolve their
     * morph targets through `resolveRouteBinding()`, and a User keyed on
     * `username` would have every one of those comparisons match an integer id
     * against a string column.
     *
     * The two collections are separate paginators (`?listings=`, `?reviews=`)
     * rather than one nested payload, so opening page 3 of the listings does not
     * reset the reviews. `reviews.index` pages the same review list from the
     * same relation with the same ordering, for a client that would rather fetch
     * than visit.
     *
     * **`resources/js/pages/profile/Show.vue` exists now**, so the props below
     * are a live contract rather than one written ahead of its consumer — the
     * note here that the component was still Phase 4's outstanding work is
     * stale. It reads `profile`, `listings`, `reviews`, `reportCategories`,
     * `reportReasons` and `reviewBounds` through ProfileHeader /
     * ProfileListings / ProfileReviews, and `commentBounds` off page props from
     * the card's comments dialog. Renaming any of them now breaks a page as
     * well as a test; see .ai/rules/resources.md.
     */
    public function show(
        Request $request,
        User $user,
        LoadProfileForDisplay $loadProfileForDisplay,
    ): Response {
        $this->authorize('view', $user);

        $profile = $loadProfileForDisplay->handle($user, $request->user());

        return Inertia::render('profile/Show', [
            'profile' => ProfileResource::make($profile['user']),
            'listings' => PetCardResource::collection($profile['listings']),
            'reviews' => ReviewResource::collection($profile['reviews']),
            'reportCategories' => ReportCategory::options(),
            'reportReasons' => ReportReason::options(),
            'reviewBounds' => $this->reviewBounds(),
            'commentBounds' => $this->commentBounds(),
        ]);
    }

    /**
     * Like a profile, or remove the like if it is already there.
     *
     * The same Action PetController::toggleLike and CommentController::toggleLike
     * run — one like path for every likeable model, which is what
     * Actions\Likes\ToggleLike exists for. `User` has implemented
     * App\Contracts\Likeable since the like vertical landed, with its own
     * `likeNotificationRecipients()`, and Database\Factories\LikeFactory has a
     * `forUser()` state; what was missing was only the route, so
     * Http\Resources\Profile\ProfileResource emitted `is_liked` that nothing
     * could ever flip.
     *
     * One toggle route rather than a like and an unlike, so a client cannot ask
     * for a transition that has already happened — the shape `pets.like`,
     * `comments.like` and `pets.status.toggle` all use.
     */
    public function toggleLike(Request $request, User $user, ToggleLike $toggleLike): RedirectResponse
    {
        $this->authorize('like', $user);

        $toggleLike->handle($user, $request->user());

        return back();
    }
}
