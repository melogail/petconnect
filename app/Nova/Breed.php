<?php

namespace App\Nova;

use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Breed extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Breed>
     */
    public static $model = \App\Models\Breed::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'name',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Category')
                ->rules(['required'])
                ->required()
                ->showCreateRelationButton(),

            Text::make('Name')
                ->required()
                ->rules(['required', 'string', 'max:255'])
                ->sortable(),

            Text::make('Name Arabic', 'name_ar')
                ->required()
                ->rules(['required', 'string', 'max:255'])
                ->sortable(),

            Slug::make('Slug')
                ->from('name')
                ->immutable()
                ->rules(['required', 'string', 'max:255'])
                ->required()
                ->hideFromIndex(),

            Images::make('Breed image', 'breeds')
                ->required()
                ->rules(['required']),

            Text::make('Description')
                ->hideFromIndex(),

            Text::make('Description Arabic', 'description_ar')
                ->hideFromIndex(),
        ];
    }

    /**
     * Get the cards available for the resource.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, \Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, \Laravel\Nova\Lenses\Lens>
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}
