<?php

namespace App\Nova;

use App\Actions\Pets\EnsureSingleFeaturedPhoto;
use App\Concerns\PetPhotoRules;
use App\Enums\HealthStatus;
use App\Enums\ListingType;
use App\Enums\PetGender;
use App\Enums\PetStatus;
use App\Models\Pet as PetModel;
use App\Nova\Actions\PurgePetListing;
use App\Nova\Metrics\NewPets;
use App\Nova\Policies\PetPolicy;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\MergeValue;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\KeyValue;
use Laravel\Nova\Fields\MorphMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;
use Laravel\Nova\ResourceTool;

/**
 * A pet listing — the thing this marketplace is for, and the thing the legacy
 * back office could not moderate at all: its Pet resource was `ID::make()`
 * and nothing else, so an admin could see that a listing existed and learn
 * nothing about it.
 *
 * ## Photos
 *
 * `Pet` registers exactly one media collection, `pets`. The cover photo is not
 * a second collection: it is the member of `pets` carrying the custom property
 * `featured => true` (Pet::FEATURED_PROPERTY). That is why the Images field
 * below declares a `customPropertiesFields` checkbox rather than there being a
 * "Featured Image" field — ticking it on one photo is what
 * Pet::featuredPhoto() reads. The legacy resource had no media field at all
 * despite the collection existing.
 *
 * ## Deleting
 *
 * `pets` soft deletes, so Nova's delete retires a listing and leaves the row,
 * its photos and its comment thread in place for moderation. Force delete is
 * still refused by the policy, because a hard delete would strand the listing's
 * comments, likes and saves (morph columns carry no foreign key), strand the
 * reports against those comments, and leave every uploaded file on disk —
 * medialibrary removes bytes from an Eloquent hook a cascade never fires.
 *
 * The permanent delete is Actions\PurgePetListing instead, running
 * Actions\Pets\PurgePet: it collects the comment subtree, clears each
 * polymorphic child explicitly and force deletes through the model, all in one
 * transaction. That is the single-pet equivalent of
 * Actions\Profiles\DeleteUserAccount, and it is what DeleteCategory's "move or
 * permanently delete those listings first" now points at — before it existed,
 * that sentence named an operation the back office did not have.
 */
class Pet extends Resource
{
    use PetPhotoRules;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<PetModel>
     */
    public static $model = PetModel::class;

    /**
     * The Nova-only policy the resource corresponds to.
     *
     * @var class-string<PetPolicy>
     */
    public static $policy = PetPolicy::class;

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
        'id', 'name', 'city', 'state', 'country',
    ];

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * `media` is here for the Photos field, which resolves through getMedia()
     * for every row the index renders — one query per row without it. See
     * .ai/rules/app.md on the medialibrary N+1 the guardrail now sees.
     *
     * @var array<int, string>
     */
    public static $with = ['user', 'category', 'breed', 'media'];

    /**
     * Settle the cover photo after an edit.
     *
     * The Images field draws its `Featured` checkbox on every photo, so an
     * admin can tick three and save. Pet::featuredPhoto() then resolves with
     * `->first()` and the cover becomes whichever photo happens to sort first —
     * unstable against a reorder or a deletion — while galleryPhotos(), which
     * rejects *every* flagged photo, silently drops the other two from the
     * gallery. Update\ReplaceFeaturedImage documents that two-flag state as a
     * transient it closes within one step; nothing stopped this form making it
     * permanent.
     *
     * Runs after the media callbacks, which is why it is `afterUpdate` and not
     * `beforeUpdate`: Nova invokes the fill callbacks — where the media field
     * writes its custom properties — and only then calls this.
     *
     * There is no `afterCreate` twin because PetPolicy::create is false; if a
     * create form is ever opened, it needs one.
     *
     * @param  PetModel  $model
     */
    public static function afterUpdate(NovaRequest $request, Model $model): void
    {
        app(EnsureSingleFeaturedPhoto::class)->handle($model);
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

            Text::make('Name')
                ->sortable()
                ->rules('required', 'string', 'max:255'),

            BelongsTo::make('Owner', 'user', User::class)
                ->sortable()
                ->filterable()
                ->searchable(),

            BelongsTo::make('Category', 'category', Category::class)
                ->sortable()
                ->filterable(),

            BelongsTo::make('Breed', 'breed', Breed::class)
                ->nullable()
                ->filterable()
                ->hideFromIndex(),

            Badge::make('Status', 'status')
                ->map([
                    PetStatus::Available->value => 'success',
                    PetStatus::Unavailable->value => 'warning',
                ])
                ->labels($this->enumLabels(PetStatus::cases()))
                ->sortable(),

            Select::make('Status', 'status')
                ->options(PetStatus::class)
                ->displayUsingLabels()
                ->onlyOnForms()
                ->rules('required'),

            Select::make('Listing Type', 'listing_type')
                ->options(ListingType::class)
                ->displayUsingLabels()
                ->sortable()
                ->filterable()
                ->rules('required'),

            Currency::make('Price')
                ->nullable()
                ->sortable()
                ->rules('nullable', 'numeric', 'min:0'),

            Number::make('Views')
                ->exceptOnForms()
                ->sortable(),

            Images::make('Photos', PetModel::PHOTO_COLLECTION)
                ->conversionOnIndexView('thumb')
                ->conversionOnDetailView('display')
                ->multiple()
                ->customPropertiesFields([
                    Boolean::make('Featured', PetModel::FEATURED_PROPERTY),
                ])
                ->singleMediaRules($this->photoFileRules())
                ->help('Tick "Featured" on the one photo that should be the cover; it is a custom property on this collection, not a separate collection. Ticking more than one is settled on save — only the first stays flagged.'),

            Textarea::make('Description')
                ->alwaysShow()
                ->rules('required', 'string'),

            new Panel('Profile', $this->profileFields()),

            new Panel('Location', $this->locationFields()),

            new Panel('Health', $this->healthFields()),

            new Panel('Timestamps', $this->timestampFields()),

            MorphMany::make('Comments', 'comments', Comment::class),
        ];
    }

    /**
     * @return array<int, Field>
     */
    protected function profileFields(): array
    {
        return [
            Select::make('Gender', 'gender')
                ->options(PetGender::class)
                ->displayUsingLabels()
                ->filterable()
                ->rules('required'),

            Text::make('Age')->rules('required', 'string', 'max:255'),
            Text::make('Color')->rules('required', 'string', 'max:255'),
            Number::make('Weight')->step(0.01)->nullable()->rules('nullable', 'numeric', 'min:0'),

            Code::make('Traits')
                ->json()
                ->nullable()
                ->help('A JSON list of strings, e.g. ["playful", "quiet"].'),

            KeyValue::make('Additional Info', 'additional_info')
                ->keyLabel('Field')
                ->valueLabel('Value')
                ->nullable(),
        ];
    }

    /**
     * @return array<int, Field>
     */
    protected function locationFields(): array
    {
        return [
            Text::make('City')->sortable()->rules('required', 'string', 'max:255'),
            Text::make('State')->rules('required', 'string', 'max:255'),
            Text::make('Country')->rules('required', 'string', 'max:255'),
            Text::make('Postal Code')->nullable()->rules('nullable', 'string', 'max:255'),
            Text::make('Address')->nullable()->rules('nullable', 'string', 'max:255'),
            Textarea::make('Detailed Address', 'detailed_address')->nullable()->rules('nullable', 'string'),
            Number::make('Latitude')->step(0.00000001)->nullable()->rules('nullable', 'numeric', 'between:-90,90'),
            Number::make('Longitude')->step(0.00000001)->nullable()->rules('nullable', 'numeric', 'between:-180,180'),
        ];
    }

    /**
     * The clinical block.
     *
     * `vaccinations` and `medications` are lists of objects with different
     * shapes — {name, date} and {name, usage} respectively (see
     * .ai/rules/models.md) — so neither fits KeyValue, which is a flat string
     * map. A JSON Code field is the honest editor for them: it round-trips
     * through the model's `array` cast without flattening either shape.
     *
     * @return array<int, Field>
     */
    protected function healthFields(): array
    {
        return [
            Select::make('Health Status', 'health_status')
                ->options(HealthStatus::class)
                ->displayUsingLabels()
                ->filterable()
                ->rules('required'),

            Boolean::make('Vaccinated')->filterable(),
            Boolean::make('Spayed / Neutered', 'spayed_neutered')->filterable(),
            Textarea::make('Special Needs', 'special_needs')->nullable()->rules('nullable', 'string'),
            Date::make('Last Vet Visit', 'last_vet_visit')->nullable(),
            Text::make('Vet Name', 'vet_name')->nullable()->rules('nullable', 'string', 'max:255'),
            Text::make('Vet Phone', 'vet_phone')->nullable()->rules('nullable', 'string', 'max:255'),

            Code::make('Vaccinations')
                ->json()
                ->nullable()
                ->help('A JSON list of {"name": "...", "date": "YYYY-MM-DD"} objects.'),

            Code::make('Medications')
                ->json()
                ->nullable()
                ->help('A JSON list of {"name": "...", "usage": "..."} objects.'),

            Code::make('Allergies')
                ->json()
                ->nullable()
                ->help('A JSON list of strings.'),
        ];
    }

    /**
     * @return array<int, Field>
     */
    protected function timestampFields(): array
    {
        return [
            DateTime::make('Created At')->exceptOnForms()->sortable()->filterable(),
            DateTime::make('Updated At')->exceptOnForms()->hideFromIndex(),
            DateTime::make('Deleted At', 'deleted_at')->exceptOnForms()->hideFromIndex(),
        ];
    }

    /**
     * Badge labels keyed by backing value, taken from the enum's own label().
     *
     * @param  array<int, PetStatus>  $cases
     * @return array<string, string>
     */
    protected function enumLabels(array $cases): array
    {
        return collect($cases)
            ->mapWithKeys(fn (PetStatus $case): array => [$case->value => $case->label()])
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
            NewPets::make(),
        ];
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
            // Resolved through the container, not ::make(): Laravel\Nova\Makeable
            // is `new static(...$arguments)` and cannot satisfy this action's
            // constructor-injected App\Actions\Pets\PurgePet, which itself
            // injects Illuminate\Pipeline\Pipeline.
            app(PurgePetListing::class)
                ->confirmText('This permanently deletes the listing and its whole comment thread, every like, save and report on it, and every uploaded photo. Retiring it with the delete button is the reversible option. This is not.')
                ->confirmButtonText('Permanently delete')
                ->cancelButtonText('Cancel'),
        ];
    }
}
