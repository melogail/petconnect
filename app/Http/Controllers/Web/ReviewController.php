<?php

namespace App\Http\Controllers\Web;

use App\Actions\Reviews\CreateReview;
use App\Actions\Reviews\DeleteReview;
use App\Actions\Reviews\ListReviews;
use App\Actions\Reviews\UpdateReview;
use App\Enums\Reviewable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Resources\Review\ReviewResource;
use App\Models\Review as ReviewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;

/**
 * Reviews written about any reviewable model.
 *
 * Every action authorizes through ReviewPolicy and then hands the work to one
 * Action or pipeline; no query or business rule lives here. Compare the legacy
 * ReviewController, whose `store` was the single worst method in the
 * application — see the note on store() below.
 *
 * ## One endpoint returns JSON rather than an Inertia page
 *
 * `index` is a data endpoint, not a page: the page a reviewable's reviews hang
 * off ships a bounded first slice, and this is how the rest is paged in without
 * a visit. `ReviewResource::collection($paginator)` gives the client
 * `data`/`links`/`meta` — paginated collections keep their envelope even though
 * JsonResource::withoutWrapping() is on application-wide (see
 * .ai/rules/resources.md). Every write action redirects back, because those are
 * posted from a page and their result belongs in that page's props.
 *
 * ## Where each check lives
 *
 * The policy decides about the acting user, and nothing else. Whether the
 * *target* may be read or written is decided in two places, neither of them
 * here, split by how the target is addressed:
 *
 * - `index` and `store` name the target in the URL, so the Action resolves it
 *   through App\Enums\Reviewable — never a class name — and a target that is
 *   gone or hidden is a ModelNotFoundException.
 * - `update` and `destroy` name a review instead, and
 *   Review::resolveRouteBinding() refuses to bind one whose reviewable has
 *   vanished. The URL never mentions the target, so the binding is the only
 *   place that can speak for it.
 *
 * The rule behind both: an endpoint bound to a child model re-derives its
 * parent's visibility rather than assuming the URL implies it.
 *
 * ## Throttling is on the route, not in the pipeline
 *
 * `reviews` is a named limiter defined in
 * AppServiceProvider::configureRateLimiters(). A review is public content that
 * notifies the person it is about, so an unthrottled loop is a notification
 * flood; the legacy routes were throttled by nothing. It is middleware rather
 * than a pipeline step on purpose: a rate limit's only meaningful outcome is a
 * 429 with Retry-After, which is transport, and .ai/rules/pipelines.md keeps
 * steps HTTP-free.
 */
class ReviewController extends Controller
{
    /**
     * A page of a reviewable's reviews.
     *
     * Public: a profile's reputation is part of the public page it belongs to,
     * so a guest reading a shared link sees it.
     */
    public function index(
        Request $request,
        Reviewable $reviewable_type,
        int $reviewable_id,
        ListReviews $listReviews,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', ReviewModel::class);

        return ReviewResource::collection($listReviews->handle(
            reviewableType: $reviewable_type,
            reviewableId: $reviewable_id,
            viewer: $request->user(),
        ));
    }

    /**
     * Submit a review.
     *
     * This method is the one the whole vertical exists to replace. The legacy
     * signature was `store(Request $request, $type, CreateReviewAction $action)`
     * on route `POST reviews/store/{type}/{reviewable_id}`, and its first line
     * was `$reviewable = $type::find($request->reviewable_id)` — a raw URL
     * segment used as a class name in a static call, with no whitelist and no
     * validation of the segment. It then read `$request->rating` and
     * `$request->comment` off an unvalidated Request, called no policy, and
     * passed the result — possibly null — into an Action that dereferenced
     * `->id` on it.
     *
     * Here `{reviewable_type}` is bound to App\Enums\Reviewable at the router,
     * so an unrecognised value is a 404 before this method runs and the
     * parameter arrives as an enum case rather than a string. `$reviewable_id`
     * is an int constrained to digits by the route. The payload is a Form
     * Request. The policy is called. Everything else — existence, visibility,
     * self-review, duplicates — is the pipeline's, against the model it
     * resolved.
     */
    public function store(
        StoreReviewRequest $request,
        Reviewable $reviewable_type,
        int $reviewable_id,
        CreateReview $createReview,
    ): RedirectResponse {
        $this->authorize('create', ReviewModel::class);

        $createReview->handle(
            author: $request->user(),
            reviewableType: $reviewable_type,
            reviewableId: $reviewable_id,
            rate: $request->rate(),
            comment: $request->comment(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review posted.')]);

        return back();
    }

    /**
     * Apply an edit to a review.
     */
    public function update(
        UpdateReviewRequest $request,
        ReviewModel $review,
        UpdateReview $updateReview,
    ): RedirectResponse {
        $this->authorize('update', $review);

        $updateReview->handle($review, $request->rate(), $request->comment());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review updated.')]);

        return back();
    }

    /**
     * Delete a review and the reports filed against it.
     *
     * RESTful `destroy` at `DELETE reviews/{review}`, not the legacy
     * `DELETE reviews/destroy/{review}`: the verb is the method, and the path
     * does not repeat it.
     */
    public function destroy(ReviewModel $review, DeleteReview $deleteReview): RedirectResponse
    {
        $this->authorize('delete', $review);

        $deleteReview->handle($review);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review removed.')]);

        return back();
    }
}
