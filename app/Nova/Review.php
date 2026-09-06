<?php

namespace App\Nova;

use App\Models\Review as ReviewModel;
use App\Nova\Actions\DeleteReview;
use App\Nova\Policies\ReviewPolicy;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphMany;
use Laravel\Nova\Fields\MorphTo;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;
use Laravel\Nova\ResourceTool;

/**
 * A 1-5 rating with an optional comment, written by one member about another.
 *
 * The second half of what makes a report actionable. Read only for the same
 * reason Comment is — a review is somebody's words about somebody else, and an
 * admin rewriting it would be forging both sides at once — with deletion as
 * the single moderator power.
 *
 * Deletion goes through Actions\Reviews\DeleteReview, not Nova's built-in
 * delete: `reports.reportable_id` is a morph column with no foreign key, so a
 * plain delete leaves every report filed against this review sitting in the
 * queue with a target that resolves to null. ReviewPolicy::delete returns
 * false to close the shortcut.
 */
class Review extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<ReviewModel>
     */
    public static $model = ReviewModel::class;

    /**
     * The Nova-only policy the resource corresponds to.
     *
     * @var class-string<ReviewPolicy>
     */
    public static $policy = ReviewPolicy::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The label a review is referred to by elsewhere in Nova.
     *
     * Same reason as App\Nova\Comment::title(): a report pointing at "12"
     * tells a moderator nothing. The rating leads because it is the part of a
     * review that is always present — `comment` is nullable. No query: both
     * attributes are on the model already.
     */
    public function title(): string
    {
        $comment = trim((string) $this->resource->comment);

        return $comment === ''
            ? sprintf('%d/5', $this->resource->rate)
            : sprintf('%d/5 — %s', $this->resource->rate, Str::limit($comment, 50));
    }

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
        'id', 'comment',
    ];

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * @var array<int, string>
     */
    public static $with = ['user', 'reviewable'];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field|Panel|ResourceTool|MergeValue>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Author', 'user', User::class)
                ->exceptOnForms()
                ->searchable(),

            MorphTo::make('About', 'reviewable')
                ->types([User::class])
                ->exceptOnForms(),

            Number::make('Rating', 'rate')
                ->exceptOnForms()
                ->sortable()
                ->filterable(),

            Textarea::make('Comment')
                ->exceptOnForms()
                ->alwaysShow(),

            // The withCount() alias indexQuery() selects, not a computed
            // closure: computed fields all serialise as `ComputedField` and
            // cannot be sorted on. See App\Nova\Comment.
            Number::make('Reports', 'reports_count')
                ->onlyOnIndex()
                ->sortable(),

            DateTime::make('Created At')
                ->exceptOnForms()
                ->sortable()
                ->filterable(),

            MorphMany::make('Reports', 'reports', Report::class),
        ];
    }

    /**
     * Build an "index" query for the given resource.
     *
     * @param  Builder<ReviewModel>  $query
     * @return Builder<ReviewModel>
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->withCount('reports');
    }

    /**
     * Get the cards available for the request.
     *
     * @return array<int, Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
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
            // Container-resolved rather than ::make(): the action
            // constructor-injects App\Actions\Reviews\DeleteReview.
            app(DeleteReview::class)
                ->confirmText('This deletes the review and every report filed against it. It cannot be undone.')
                ->confirmButtonText('Delete review')
                ->cancelButtonText('Cancel'),
        ];
    }
}
