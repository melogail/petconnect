<?php

namespace App\Nova;

use App\Concerns\ProfileValidationRules;
use App\Nova\Actions\DeactivateUser;
use App\Nova\Actions\DeleteUserAccount;
use App\Nova\Actions\ReactivateUser;
use App\Nova\Metrics\NewUsers;
use App\Nova\Policies\UserPolicy;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Http\Resources\MergeValue;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Card;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Email;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
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
 * A member account.
 *
 * Three things are deliberately absent from the form and are worth stating,
 * because each of them looks like an oversight:
 *
 * - **No `Password` field.** An admin who could set a member's password could
 *   silently take over the account, and it would bypass the password reset
 *   broker entirely. Support resets go through the application's own forgot
 *   password flow.
 * - **`Email` is display only.** Writing it here would leave
 *   `email_verified_at` pointing at an address the user never confirmed, which
 *   is the one invariant MustVerifyEmail rests on.
 * - **`is_active` is display only.** It is outside #[Fillable] and outside
 *   every Form Request precisely so that exactly one thing writes it. That
 *   thing is Actions\DeactivateUser / Actions\ReactivateUser below, which set
 *   the column explicitly. A Nova field would quietly become a second writer,
 *   because Nova fills by direct property assignment and mass assignment
 *   guarding never sees it.
 *
 * Creating and deleting are both handled by the policy rather than by Nova's
 * built-ins — see App\Nova\Policies\UserPolicy for why.
 *
 * ## The field rules are the application's rules, not a second set
 *
 * Every writable field below takes its rules from
 * App\Concerns\ProfileValidationRules — the same trait
 * Http\Requests\Profile\UpdateProfileRequest and Actions\Fortify\CreateNewUser
 * validate through. Restating them here is what this resource used to do, and
 * the two sets had already drifted apart in four places: `username` was
 * `nullable|string|max:255` against the trait's `alpha_dash|min:3|max:50`, so a
 * PUT wrote `not a valid handle!!!` into a column the app treats as URL-safe;
 * `locale` was `required|string` against the trait's
 * `Rule::in(config('petconnect.locales.supported'))`, so `klingon` persisted
 * and User::preferredLocale() silently fell back for that account forever;
 * `bio` was `max:2000` against a configured ceiling of 1000; and the
 * latitude/longitude pair had lost its `required_with`, so half a coordinate
 * could be saved. All four were written live and verified.
 *
 * The Select's options and the `locale` rule now come from the same config key,
 * which is what localeOptions() below always claimed but could not enforce on
 * its own — options are a rendering concern and never reach the validator.
 */
class User extends Resource
{
    use ProfileValidationRules;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\User>
     */
    public static $model = \App\Models\User::class;

    /**
     * The Nova-only policy the resource corresponds to.
     *
     * @var class-string<UserPolicy>
     */
    public static $policy = UserPolicy::class;

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
    public static $group = 'Accounts';

    /**
     * The columns that should be searched.
     *
     * @var array<int, string>
     */
    public static $search = [
        'id', 'name', 'username', 'email',
    ];

    /**
     * The relationships that should be eager loaded on index queries.
     *
     * `media` is here because the Avatar field resolves through getMedia() for
     * every row the index renders, which is one query per row without it — the
     * medialibrary N+1 .ai/rules/app.md is about, and the one the Nova
     * exemption in AppServiceProvider was silently absorbing.
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

            Images::make('Avatar', 'users')
                ->conversionOnIndexView('thumb')
                ->conversionOnDetailView('display')
                ->singleMediaRules($this->avatarFileRules())
                ->help('The member\'s own avatar; replacing it here overwrites what they uploaded.'),

            Text::make('Name')
                ->sortable()
                ->rules($this->nameRules()),

            Text::make('Username')
                ->sortable()
                ->nullable()
                ->rules($this->usernameRules($this->editedUserId($request))),

            Email::make('Email')
                ->exceptOnForms()
                ->sortable()
                ->copyable(),

            Boolean::make('Verified', fn (): bool => $this->resource->isVerified())
                ->exceptOnForms(),

            Boolean::make('Active', 'is_active')
                ->exceptOnForms()
                ->sortable()
                ->filterable()
                ->help('Changed with the Deactivate / Reactivate actions, never by hand.'),

            Textarea::make('Bio')
                ->nullable()
                ->rules($this->bioRules())
                ->alwaysShow(),

            Text::make('Phone')
                ->hideFromIndex()
                ->nullable()
                ->rules($this->phoneRules()),

            Text::make('Location')
                ->exceptOnForms()
                ->hideFromIndex(),

            Select::make('Locale')
                ->options($this->localeOptions())
                ->displayUsingLabels()
                ->hideFromIndex()
                ->rules($this->localeRules(required: true)),

            new Panel('Address', $this->addressFields()),

            new Panel('Activity', $this->activityFields()),

            HasMany::make('Pets', 'pets', Pet::class),

            HasMany::make('Comments', 'comments', Comment::class),

            MorphMany::make('Reviews Received', 'reviews', Review::class),

            HasMany::make('Reviews Written', 'givenReviews', Review::class),

            HasMany::make('Reports Filed', 'reports', Report::class),
        ];
    }

    /**
     * The postal / geographic fields, collapsed into their own panel.
     *
     * @return array<int, Field>
     */
    protected function addressFields(): array
    {
        return [
            Text::make('Country')->nullable()->rules($this->addressLineRules()),
            Text::make('State')->nullable()->rules($this->addressLineRules()),
            Text::make('City')->nullable()->rules($this->addressLineRules()),
            Text::make('Address')->nullable()->rules($this->addressLineRules()),
            Number::make('Latitude', 'lat')->step(0.00000001)->nullable()->rules($this->latitudeRules()),
            Number::make('Longitude', 'lng')->step(0.00000001)->nullable()->rules($this->longitudeRules()),
            Text::make('Timezone')->nullable()->rules($this->timezoneRules()),
        ];
    }

    /**
     * The id the uniqueness rules must ignore, or null on a create form.
     *
     * Read off the request rather than off `$this->resource`, because fields()
     * is resolved for the creation form against a blank model whose key is
     * null, and `Rule::unique()->ignore(null)` is the plain check that form
     * needs. On an update Nova puts the route's resource id here.
     */
    protected function editedUserId(NovaRequest $request): ?int
    {
        $resourceId = $request->resourceId;

        return is_numeric($resourceId) ? (int) $resourceId : null;
    }

    /**
     * Read-only signals a moderator triages an account by.
     *
     * @return array<int, Field>
     */
    protected function activityFields(): array
    {
        return [
            DateTime::make('Email Verified At')->exceptOnForms()->sortable(),
            DateTime::make('Last Seen At')->exceptOnForms()->sortable(),
            DateTime::make('Two Factor Confirmed At', 'two_factor_confirmed_at')->exceptOnForms()->hideFromIndex(),
            DateTime::make('Created At')->exceptOnForms()->sortable()->filterable(),
            DateTime::make('Updated At')->exceptOnForms()->hideFromIndex(),
        ];
    }

    /**
     * The locales the application will actually honour.
     *
     * Drawn from config rather than hardcoded, because User::preferredLocale()
     * falls back to the app locale for anything outside this list — a Select
     * offering more than the config does would silently write dead values.
     *
     * Options alone never stopped that: they are a rendering concern and the
     * validator never sees them, which is how `locale = klingon` was written
     * through the API. The enforcement is ProfileValidationRules::localeRules(),
     * which reads the same key; this method only decides what the control
     * shows.
     *
     * @return array<string, string>
     */
    protected function localeOptions(): array
    {
        /** @var list<string> $supported */
        $supported = config('petconnect.locales.supported', ['en']);

        return collect($supported)
            ->mapWithKeys(fn (string $locale): array => [$locale => strtoupper($locale)])
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
            NewUsers::make()->refreshWhenActionsRun(),
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
            DeactivateUser::make()
                ->confirmText('Deactivating ends the account\'s session on its next request, hides its public profile and refuses incoming messages. Existing listings, comments and reviews stay published.')
                ->confirmButtonText('Deactivate')
                ->cancelButtonText('Keep active'),

            ReactivateUser::make()
                ->confirmText('The account can sign in again and its public profile becomes visible.')
                ->confirmButtonText('Reactivate')
                ->cancelButtonText('Leave deactivated'),

            // Resolved through the container, not ::make(): Laravel\Nova\Makeable
            // is `new static(...$arguments)` and cannot satisfy this action's
            // constructor-injected App\Actions\Profiles\DeleteUserAccount,
            // which itself injects Illuminate\Pipeline\Pipeline.
            app(DeleteUserAccount::class)
                ->confirmText('This permanently deletes the account and every listing, comment, review, like, save, report, notification and uploaded file that belongs to it. It cannot be undone.')
                ->confirmButtonText('Delete account')
                ->cancelButtonText('Cancel'),
        ];
    }
}
