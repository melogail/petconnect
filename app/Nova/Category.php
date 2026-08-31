<?php

namespace App\Nova;

use App\Nova\Actions\DeleteCategory;
use App\Nova\Policies\CategoryPolicy;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Resources\MergeValue;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;
use Laravel\Nova\ResourceTool;

/**
 * A species-level grouping (dog, cat, bird...).
 *
 * The Arabic columns are flat siblings (`name_ar`, `description_ar`) rather
 * than a translations table, so they are plain fields here.
 *
 * **Deleting a category never goes through Nova's built-in delete.**
 * `pets.category_id` is `restrictOnDelete` and `pets` soft deletes, so a
 * category whose listings have all been "deleted" still has rows pointing at
 * it and the driver raises a raw foreign key violation. CategoryPolicy::delete
 * therefore returns false, which removes the built-in delete button entirely,
 * and Actions\DeleteCategory does the work behind a check that produces a
 * sentence instead of a stack trace. See .ai/rules/migrations.md.
 */
class Category extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Category>
     */
    public static $model = \App\Models\Category::class;

    /**
     * The Nova-only policy the resource corresponds to.
     *
     * @var class-string<CategoryPolicy>
     */
    public static $policy = CategoryPolicy::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The logical group associated with the resource.
     *
     * @var string
     */
    public static $group = 'Catalog';

    /**
     * The columns that should be searched.
     *
     * @var array<int, string>
     */
    public static $search = [
        'id', 'name', 'name_ar', 'slug',
    ];

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * `media` is here for the Image field, which resolves through getMedia()
     * for every row the index renders — one query per row without it. See
     * .ai/rules/app.md on the medialibrary N+1 the guardrail now sees.
     *
     * @var array<int, string>
     */
    public static $with = ['media'];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field|Panel|ResourceTool|MergeValue>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Images::make('Image', 'categories')
                ->conversionOnIndexView('thumb')
                ->conversionOnDetailView('display')
                ->singleMediaRules(['image', 'max:5120']),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'string', 'max:255'),

            Text::make('Name (Arabic)', 'name_ar')
                ->sortable()
                ->nullable()
                ->rules('nullable', 'string', 'max:255'),

            Slug::make('Slug')
                ->from('name')
                ->rules('required', 'string', 'max:255')
                ->creationRules('unique:categories,slug')
                ->updateRules('unique:categories,slug,{{resourceId}}')
                ->hideFromIndex(),

            Textarea::make('Description')
                ->nullable()
                ->rules('nullable', 'string'),

            Textarea::make('Description (Arabic)', 'description_ar')
                ->nullable()
                ->rules('nullable', 'string'),

            // The withCount() aliases indexQuery() selects, not computed
            // closures: computed fields all serialise as `ComputedField` and
            // cannot be sorted on. See App\Nova\Comment.
            Number::make('Breeds', 'breeds_count')
                ->onlyOnIndex()
                ->sortable(),

            Number::make('Listings', 'pets_count')
                ->onlyOnIndex()
                ->sortable()
                ->help('Includes soft-deleted listings, which still hold their category_id.'),

            DateTime::make('Created At')
                ->exceptOnForms()
                ->hideFromIndex()
                ->filterable(),

            HasMany::make('Breeds', 'breeds', Breed::class),

            HasMany::make('Pets', 'pets', Pet::class),
        ];
    }

    /**
     * Build an "index" query for the given resource.
     *
     * The two count columns are aggregated in the index query rather than
     * counted per row: the index shows 25 categories at a time and a
     * `withCount` closure on the field would be 50 extra queries a page.
     * `pets` is counted `withTrashed()` on purpose — a soft-deleted pet still
     * holds its `category_id`, so it is exactly the number that decides
     * whether the category can be deleted.
     *
     * The closure a `withCount` constraint receives is an Eloquent Builder, not
     * a Relation — `Builder::withAggregate()` resolves the relation first and
     * hands the constraint to `callScope()`. That is the opposite of an eager
     * load constraint (see .ai/rules/app.md), so the type hint is different too.
     *
     * @param  Builder<\App\Models\Category>  $query
     * @return Builder<\App\Models\Category>
     */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->withCount([
            'breeds',
            'pets' => fn (EloquentBuilder $pets): EloquentBuilder => $pets->withTrashed(),
        ]);
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
            DeleteCategory::make()
                ->confirmText('A category can only be deleted once no listing — including soft-deleted ones — still points at it. Its breeds are deleted with it.')
                ->confirmButtonText('Delete category')
                ->cancelButtonText('Cancel'),
        ];
    }
}
