<?php

namespace App\Nova;

use App\Enums\ReportCategory;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Report as ReportModel;
use App\Nova\Actions\ChangeReportStatus;
use App\Nova\Actions\PurgeOrphanedReports;
use App\Nova\Filters\ReportCategoryFilter;
use App\Nova\Filters\ReportReasonFilter;
use App\Nova\Filters\ReportStatusFilter;
use App\Nova\Metrics\PendingReports;
use App\Nova\Metrics\ReportsByStatus;
use App\Nova\Policies\ReportPolicy;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Resources\MergeValue;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;
use Laravel\Nova\ResourceTool;

/**
 * The moderation queue. There was no Report resource in the legacy back office
 * at all, so the whole reporting feature had nowhere to be acted on.
 *
 * ## `status` is the moderator's decision and nothing else writes it
 *
 * `reports.status` is deliberately outside App\Models\Report's #[Fillable] —
 * the column defaults to `pending` and a Form Request forwarding validated()
 * must never be able to file a report that is already resolved. Every field on
 * this resource is therefore read only, including status: the only way to move
 * a report is Actions\ChangeReportStatus, which assigns the property
 * explicitly. There is no create form and no update form (see
 * App\Nova\Policies\ReportPolicy); a report is user-filed evidence, not
 * something an admin authors or edits.
 *
 * ## Reaching the target
 *
 * `reportable_type` holds a morph alias (`comment` or `review`, the backing
 * values of App\Enums\Reportable), so the MorphTo below lists exactly the two
 * Nova resources those aliases map to. It is the link a moderator follows from
 * "somebody reported this" to the thing itself, where Comment and Review each
 * carry their own delete action.
 *
 * A report whose target has already been deleted resolves to a null
 * `reportable`. Those rows are the ones the cleanup Actions exist to prevent
 * (.ai/rules/actions.md); when one does appear, it shows as an empty target and
 * is cleared with Actions\PurgeOrphanedReports, which refuses to touch a report
 * whose target still resolves. Nova's own delete button is off for this
 * resource (ReportPolicy::delete) so that a bulk "select all" cannot empty the
 * queue.
 */
class Report extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<ReportModel>
     */
    public static $model = ReportModel::class;

    /**
     * The Nova-only policy the resource corresponds to.
     *
     * @var class-string<ReportPolicy>
     */
    public static $policy = ReportPolicy::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'Moderation';

    /**
     * The columns that should be searched.
     *
     * @var array<int, string>
     */
    public static $search = [
        'id', 'description',
    ];

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * @var array<int, string>
     */
    public static $with = ['user', 'reportable'];

    /**
     * Order the queue by what still needs deciding, then oldest first.
     *
     * Nova's own default is newest id first, which is the wrong end of a
     * moderation queue: the report that has been waiting longest is the one
     * that should be looked at next. Sorting on `created_at` alone was the
     * wrong end of a different axis, though, and only looked right on an empty
     * queue. It orders the **whole table**, so once there is any history page 1
     * is the oldest reports ever filed — overwhelmingly resolved and rejected
     * ones — and everything actually pending sinks towards the last page. A
     * moderator opening the queue has to page to the end to find work.
     *
     * Pending first fixes that without hiding anything, and it is a sort rather
     * than a default filter on purpose: a filtered default would fight the
     * status filter the resource already offers, and Nova has no way to say
     * "this filter is in force" in a way an admin can see.
     *
     * The primary key is the last tiebreaker because `created_at` is not
     * unique. Reports filed in the same second — which is what a spree looks
     * like, and exactly what a moderator is paging through — have no defined
     * order without it, so a row can appear on two pages or on none.
     *
     * @param  Builder<ReportModel>  $query
     * @return Builder<ReportModel>
     */
    public static function defaultOrderings(Builder $query): Builder
    {
        $model = $query->getModel();

        return $query
            ->orderByRaw(
                sprintf('case when %s = ? then 0 else 1 end', $model->qualifyColumn('status')),
                [ReportStatus::Pending->value],
            )
            ->orderBy($model->getQualifiedCreatedAtColumn())
            ->orderBy($model->getQualifiedKeyName());
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field|Panel|ResourceTool|MergeValue>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Badge::make('Status', 'status')
                ->map([
                    ReportStatus::Pending->value => 'warning',
                    ReportStatus::Reviewed->value => 'info',
                    ReportStatus::Resolved->value => 'success',
                    ReportStatus::Rejected->value => 'danger',
                ])
                ->labels($this->labelsFor(ReportStatus::cases()))
                ->sortable(),

            MorphTo::make('Reported', 'reportable')
                ->types([
                    Comment::class,
                    Review::class,
                ])
                ->exceptOnForms(),

            BelongsTo::make('Reported By', 'user', User::class)
                ->exceptOnForms()
                ->searchable(),

            Select::make('Category', 'category')
                ->options(ReportCategory::class)
                ->displayUsingLabels()
                ->exceptOnForms()
                ->sortable(),

            Select::make('Reason', 'reason')
                ->options(ReportReason::class)
                ->displayUsingLabels()
                ->exceptOnForms()
                ->sortable(),

            Textarea::make('Description')
                ->exceptOnForms()
                ->alwaysShow(),

            KeyValue::make('Metadata', 'metadata')
                ->keyLabel('Field')
                ->valueLabel('Value')
                ->exceptOnForms()
                ->onlyOnDetail(),

            DateTime::make('Filed At', 'created_at')
                ->exceptOnForms()
                ->sortable()
                ->filterable(),

            DateTime::make('Last Decision At', 'updated_at')
                ->exceptOnForms()
                ->hideFromIndex(),
        ];
    }

    /**
     * Badge labels keyed by backing value, taken from the enum's own label().
     *
     * @param  array<int, ReportStatus>  $cases
     * @return array<string, string>
     */
    protected function labelsFor(array $cases): array
    {
        return collect($cases)
            ->mapWithKeys(fn (ReportStatus $case): array => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Get the cards available for the request.
     *
     * @return array<int, Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [
            PendingReports::make()->refreshWhenActionsRun(),
            ReportsByStatus::make()->refreshWhenActionsRun(),
        ];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [
            ReportStatusFilter::make(),
            ReportCategoryFilter::make(),
            ReportReasonFilter::make(),
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [
            ChangeReportStatus::make(),

            PurgeOrphanedReports::make()
                ->confirmText('This permanently deletes the selected reports. It refuses to run if any of them still points at a comment or review that exists — those are evidence and belong in Change Status.')
                ->confirmButtonText('Purge orphans')
                ->cancelButtonText('Cancel'),
        ];
    }
}
