<?php

namespace App\Nova\Actions;

use App\Actions\Reviews\DeleteReview as DeleteReviewAction;
use App\Models\Review;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Remove a review and the reports filed against it.
 *
 * A thin adapter onto App\Actions\Reviews\DeleteReview.
 * `reports.reportable_id` is a morph column and carries no foreign key, so
 * nothing in the database removes a review's reports when the review goes.
 * Left behind they are moderation-queue rows whose `reportable()` resolves to
 * null — which is the very thing this resource exists to let somebody clear.
 * The Action deletes both inside one transaction.
 *
 * Nova's built-in delete is refused by App\Nova\Policies\ReviewPolicy::delete
 * so this is the only route.
 */
class DeleteReview extends DestructiveAction
{
    public function __construct(private readonly DeleteReviewAction $deleteReview) {}

    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name = 'Delete Review (with reports)';

    /**
     * Perform the action on the given models.
     *
     * @param  Collection<int, Review>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $models->each(function (Review $review): void {
            $this->deleteReview->handle($review);
        });

        return ActionResponse::message(sprintf(
            '%d review(s) deleted, along with the reports filed against them.',
            $models->count(),
        ));
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
