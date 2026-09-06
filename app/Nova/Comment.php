<?php

namespace App\Nova;

use App\Models\Comment as CommentModel;
use App\Nova\Actions\DeleteCommentThread;
use App\Nova\Policies\CommentPolicy;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
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
 * User-written comments. Along with Review, this is what makes a report
 * actionable: a moderator arrives here from Report::$reportable and decides
 * what to do about the content itself.
 *
 * ## Read only, except for deleting
 *
 * A comment is somebody's words. An admin who could edit them would be putting
 * text in a member's mouth under that member's name, with no trace, so
 * CommentPolicy refuses create and update and this resource has no writable
 * field. The one moderator power is removal, and it is
 * Actions\DeleteCommentThread rather than Nova's built-in delete.
 *
 * ## Why the built-in delete is off
 *
 * `comments.parent_id` cascades, so deleting a root comment takes its whole
 * subtree with it at the database level — firing no Eloquent events, and
 * leaving every like and report on every descendant behind as a row whose
 * target resolves to null. Actions\Comments\DeleteComment collects the subtree
 * first and clears those polymorphic children inside one transaction. The Nova
 * action delegates to it; the policy's `delete` returns false so nothing else
 * can take the shortcut. Same shape as the User rule in .ai/rules/actions.md.
 */
class Comment extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<CommentModel>
     */
    public static $model = CommentModel::class;

    /**
     * The Nova-only policy the resource corresponds to.
     *
     * @var class-string<CommentPolicy>
     */
    public static $policy = CommentPolicy::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The label a comment is referred to by elsewhere in Nova.
     *
     * Overridden because `$title = 'id'` makes a report's target render as a
     * bare number — "74" — which tells a moderator nothing about what was
     * reported. An excerpt of the comment itself is the whole point of the
     * link. Reads an attribute already on the model, so it costs no query, and
     * falls back to the id for an empty body.
     */
    public function title(): string
    {
        $content = trim((string) $this->resource->content);

        return $content === ''
            ? '#'.$this->resource->getKey()
            : Str::limit($content, 60);
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
        'id', 'content',
    ];

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * @var array<int, string>
     */
    public static $with = ['user', 'commentable'];

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

            Textarea::make('Content')
                ->exceptOnForms()
                ->alwaysShow(),

            MorphTo::make('On', 'commentable')
                ->types([Pet::class])
                ->exceptOnForms(),

            BelongsTo::make('In Reply To', 'parent', self::class)
                ->exceptOnForms()
                ->nullable()
                ->hideFromIndex(),

            // Named after the withCount() aliases indexQuery() selects rather
            // than built from a closure. A closure would make these *computed*
            // fields, and every computed field in Nova serialises under the
            // literal attribute `ComputedField` — three of them on one row
            // collide, and ->sortable() would emit `order by ComputedField`.
            // Selecting the alias makes the sort a real ORDER BY.
            Number::make('Replies', 'replies_count')
                ->onlyOnIndex()
                ->sortable(),

            Number::make('Reports', 'reports_count')
                ->onlyOnIndex()
                ->sortable(),

            Number::make('Likes', 'likes_count')
                ->onlyOnIndex()
                ->sortable(),

            DateTime::make('Created At')
                ->exceptOnForms()
                ->sortable()
                ->filterable(),

            HasMany::make('Replies', 'replies', self::class),

            MorphMany::make('Reports', 'reports', Report::class),
        ];
    }

    /**
     * Build an "index" query for the given resource.
     *
     * The three counters are aggregated here rather than resolved per row: a
     * 25-row index would otherwise issue 75 extra queries, and the reports
     * count in particular is the column a moderator sorts by.
     *
     * @param  Builder<CommentModel>  $query
     * @return Builder<CommentModel>
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->withCount(['replies', 'reports', 'likes']);
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
            // constructor-injects App\Actions\Comments\DeleteComment, which
            // injects Illuminate\Pipeline\Pipeline.
            app(DeleteCommentThread::class)
                ->confirmText('This deletes the comment, every reply beneath it, and the likes and reports on all of them. It cannot be undone.')
                ->confirmButtonText('Delete comment')
                ->cancelButtonText('Cancel'),
        ];
    }
}
