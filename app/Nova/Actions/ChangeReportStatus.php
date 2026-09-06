<?php

namespace App\Nova\Actions;

use App\Enums\ReportStatus;
use App\Models\Report;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

/**
 * Move one or more reports to a new moderation status.
 *
 * The only writer of `reports.status` in the application. The column is
 * deliberately outside App\Models\Report's #[Fillable] — it is a moderator
 * decision, not something a request bag may carry — so this assigns the
 * property directly instead of going anywhere near `update([...])` or
 * `fill()`. Reaching for mass assignment here would either be silently
 * discarded or, if somebody "fixed" it by adding the column to #[Fillable],
 * would hand the same power to every Form Request that forwards validated().
 *
 * It is one action with a Select rather than four one-status actions, so that
 * adding a case to App\Enums\ReportStatus needs no new class and no edit here:
 * the options come from the enum.
 *
 * ## The selection is one transaction, and a failure is a sentence
 *
 * The transaction was already here; the catch is what makes it legible. Without
 * it a throw on the third of five rolled the writes back correctly and still
 * handed the admin a 500 with a stack trace, so the state was right and
 * unreadable — the shape .ai/rules/nova-actions.md makes non-negotiable for
 * every bulk action, and the one DeleteCategory and DeleteReview have. The
 * message says "changed" rather than "deleted" because this action writes a
 * column and removes nothing.
 *
 * `ReportStatus::from()` is resolved **inside** the try on purpose. The Select
 * validates against `Rule::enum`, but the field is not the only way a value
 * reaches `handle()` — an action request carries the field bag, and `from()`
 * throws a `ValueError`, not a validation failure, on anything the enum does
 * not know. Outside the try that escaped as a 500 before a single row was
 * touched; inside it the admin reads the same sentence, which is true — nothing
 * was changed. `$status` is assigned and used entirely within the try, and the
 * success message returns from there too, so it is never read in a scope where
 * the `from()` call might not have completed.
 */
class ChangeReportStatus extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name = 'Change Status';

    /**
     * Perform the action on the given models.
     *
     * @param  Collection<int, Report>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        try {
            $status = ReportStatus::from((string) $fields->status);

            DB::transaction(function () use ($models, $status): void {
                $models->each(function (Report $report) use ($status): void {
                    $report->status = $status;
                    $report->save();
                });
            });

            return ActionResponse::message(sprintf(
                '%d report(s) marked as %s.',
                $models->count(),
                $status->label(),
            ));
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger(
                'Nothing was changed. One of the selected reports could not be moved to the new status, so the whole selection was rolled back. The failure has been logged.',
            );
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Select::make('Status', 'status')
                ->options(ReportStatus::class)
                ->displayUsingLabels()
                ->default(ReportStatus::Reviewed->value)
                ->rules('required', Rule::enum(ReportStatus::class))
                ->help('Pending is the state a report arrives in; Reviewed, Resolved and Rejected are decisions.'),
        ];
    }
}
