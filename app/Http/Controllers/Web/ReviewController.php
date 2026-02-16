<?php

namespace App\Http\Controllers\Web;

use App\Actions\CreateReviewAction;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;


class ReviewController extends Controller
{
    public function store(Request $request, $type, CreateReviewAction $action)
    {
        $reviewable = $type::find($request->reviewable_id);
        $reviewer = auth()->user();
        $rating = $request->rating;
        $comment = $request->comment;

        $action->make($reviewable, $reviewer, $rating, $comment);

        return redirect()->back();
    }

    public function update(Request $request, Review $review)
    {
        Gate::authorize('update', $review);

        $review->update([
            'rate' => $request->rating,
            'comment' => $request->comment,
        ]);

        return redirect()->back();
    }

    public function destroy(Review $review)
    {
        Gate::authorize('delete', $review);

        $review->delete();

        return redirect()->back();
    }
}
