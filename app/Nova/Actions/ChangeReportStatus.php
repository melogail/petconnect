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
