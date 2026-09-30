<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reviews\CreateReview;
use App\Actions\Reviews\DeleteReview;
use App\Actions\Reviews\ListReviews;
use App\Actions\Reviews\UpdateReview;
use App\Concerns\ResolvesRequestUser;
use App\Enums\Reviewable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Resources\Review\ReviewResource;
use App\Models\Review as ReviewModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Reviews, for the mobile app — Web\ReviewController with JSON answers.
 */
class ReviewController extends Controller
{
    use ResolvesRequestUser;

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
            viewer: $this->viewer($request),
        ));
    }

    public function store(
        StoreReviewRequest $request,
        Reviewable $reviewable_type,
        int $reviewable_id,
        CreateReview $createReview,
    ): JsonResponse {
        $this->authorize('create', ReviewModel::class);

        $review = $createReview->handle(
            author: $this->actor($request),
            reviewableType: $reviewable_type,
            reviewableId: $reviewable_id,
            rate: $request->rate(),
            comment: $request->comment(),
        );

        return ReviewResource::make($review->loadMissing('user.media'))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateReviewRequest $request,
        ReviewModel $review,
        UpdateReview $updateReview,
    ): ReviewResource {
        $this->authorize('update', $review);

        $updateReview->handle($review, $request->rate(), $request->comment());

        return ReviewResource::make($review->loadMissing('user.media'));
    }

    public function destroy(ReviewModel $review, DeleteReview $deleteReview): Response
    {
        $this->authorize('delete', $review);

        $deleteReview->handle($review);

        return response()->noContent();
    }
}
