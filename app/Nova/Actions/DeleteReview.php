<?php

namespace App\Nova\Actions;

use App\Actions\Reviews\DeleteReview as DeleteReviewAction;
use App\Models\Review;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

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
 *
 * ## The selection is one transaction, and a failure is a sentence
 *
 * The Action's own transaction covers one review and its reports. Nova hands
 * this handle() the **whole selection**, and a bare `$models->each(...)` would
 * leave a throw on the third of five as two reviews deleted, three intact and
 * an unreadable 500 for the admin — the exact failure
 * .ai/rules/nova-policies.md records as already fixed once, on
 * DeleteUserAccount. Same shape here: DB::transaction around the selection,
 * `catch (Throwable)` returning ActionResponse::danger() so the admin is told
 * nothing happened rather than being left guessing which half did.
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
        try {
            DB::transaction(function () use ($models): void {
                $models->each(function (Review $review): void {
                    $this->deleteReview->handle($review);
                });
            });
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger(
                'Nothing was deleted. One of the selected reviews could not be removed, so the whole selection was rolled back. The failure has been logged.',
            );
        }

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
