<?php

namespace App\Nova;

use App\Concerns\TaxonomyImageRules;
use App\Nova\Policies\BreedPolicy;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Http\Resources\MergeValue;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;
use Laravel\Nova\ResourceTool;

/**
 * A breed within a category.
 *
 * `breeds` is unique on (category_id, slug), not on slug alone, so the slug
 * rule has to be scoped to the submitted category rather than to the table —
 * a plain `unique:breeds,slug` would refuse "labrador" under Cats because it
 * already exists under Dogs. See slugRule().
 *
 * The image field's rules come from App\Concerns\TaxonomyImageRules, shared
 * with App\Nova\Category, rather than being restated here: they used to be a
 * bare `['image', 'max:5120']`, which accepts formats GD cannot convert and so
 * serves the raw upload where a crop was meant to be (.ai/rules/nova.md).
 */
class Breed extends Resource
{
    use TaxonomyImageRules;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Breed>
     */
    public static $model = \App\Models\Breed::class;

    /**
     * The Nova-only policy the resource corresponds to.
     *
     * @var class-string<BreedPolicy>
     */
    public static $policy = BreedPolicy::class;

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
    public static $with = ['category', 'media'];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, Field|Panel|ResourceTool|MergeValue>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make('Category', 'category', Category::class)
                ->sortable()
                ->filterable()
                ->showCreateRelationButton()
                ->rules('required'),

            Images::make('Image', 'breeds')
                ->conversionOnIndexView('thumb')
                ->conversionOnDetailView('display')
                ->singleMediaRules($this->taxonomyImageFileRules()),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'string', 'max:255'),

            Text::make('Name (Arabic)', 'name_ar')
                ->sortable()
                ->nullable()
                ->rules('nullable', 'string', 'max:255'),

            Slug::make('Slug')
                ->from('name')
                ->rules('required', 'string', 'max:255', $this->slugRule($request))
                ->hideFromIndex(),

            Textarea::make('Description')
                ->nullable()
                ->rules('nullable', 'string'),

            Textarea::make('Description (Arabic)', 'description_ar')
                ->nullable()
                ->rules('nullable', 'string'),

            DateTime::make('Created At')
                ->exceptOnForms()
                ->hideFromIndex()
                ->filterable(),

            HasMany::make('Pets', 'pets', Pet::class),
        ];
    }

    /**
     * Uniqueness for the slug, scoped to the category the form is submitting.
     *
     * The category comes off the request rather than off `$this->resource`,
     * because on an update the admin may be moving the breed to a different
     * category in the same submission, and the constraint that has to hold is
     * the one for the *new* parent.
     */
    protected function slugRule(NovaRequest $request): Unique
    {
        $rule = Rule::unique('breeds', 'slug')
            ->where('category_id', $request->input('category'));

        $resourceId = $request->resourceId;

        return $resourceId === null ? $rule : $rule->ignore($resourceId);
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
        return [];
    }
}
