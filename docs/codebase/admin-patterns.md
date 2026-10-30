# Admin patterns (Nova)

Nova is the moderation tool. Its resources are read-mostly; irreversible operations are explicit actions that reuse the application's own deletion pipelines.

## Resource

`app/Nova/Review.php`. Fields are shown, not edited; counts come from `withCount()` in `indexQuery()` so they can be sorted.

```php
public function fields(NovaRequest $request): array
{
    return [
        ID::make()->sortable(),
        BelongsTo::make('Author', 'user', User::class)->exceptOnForms()->searchable(),
        MorphTo::make('About', 'reviewable')->types([User::class])->exceptOnForms(),
        Number::make('Rating', 'rate')->exceptOnForms()->sortable()->filterable(),
        Textarea::make('Comment')->exceptOnForms()->alwaysShow(),
        Number::make('Reports', 'reports_count')->onlyOnIndex()->sortable(),
        DateTime::make('Created At')->exceptOnForms()->sortable()->filterable(),
        MorphMany::make('Reports', 'reports', Report::class),
    ];
}
```

## Policy: take the built-in delete away

`app/Nova/Policies/ReviewPolicy.php`. Built-in create, update and delete return `false`; the destructive action is the only path.

```php
public function delete(Admin $admin, ReviewResource $resource): bool
{
    return false;
}

public function runDestructiveAction(Admin $admin, ReviewResource $resource): bool
{
    return true;
}
```

Nova answers a forbidden delete with 200 and an empty result, so tests assert that nothing was removed rather than a status code.

## Destructive action

`app/Nova/Actions/DeleteReview.php`. One transaction for the whole selection, the same Action the web uses, and a report plus a danger response on failure.

```php
class DeleteReview extends DestructiveAction
{
    public function __construct(private readonly DeleteReviewAction $deleteReview) {}

    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        try {
            DB::transaction(function () use ($models): void {
                $models->each(fn (Review $review) => $this->deleteReview->handle($review));
            });
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger('Nothing was deleted. … The failure has been logged.');
        }

        return ActionResponse::message(sprintf('%d review(s) deleted, along with the reports filed against them.', $models->count()));
    }
}
```

The same shape applies to `DeleteUserAccount`, `PurgePetListing`, `DeleteCommentThread`, `DeleteCategory`, `DeactivateUser`, `ReactivateUser`, `ChangeReportStatus` and `PurgeOrphanedReports`.

## Gate and guard

`NovaServiceProvider` sets the gate with an `Admin` type hint and relies on `config('nova.guard') === 'admin'`. Both are kept on purpose: the guard decides who can log in, the gate decides who can see Nova once logged in.

## Testing Nova

```php
$this->actingAs($admin, 'admin')
    ->deleteJson('/nova-api/users', ['resources' => [$user->getKey()]])
    ->assertOk();

$this->assertModelExists($user);
```

Run an action with `POST /nova-api/{resource}/action?action={uri-key}` and `resources` in the body. `report()` inside an action is not observable through `Exceptions::fake()` because Nova swaps the exception handler for the request.

## Adding a resource

1. `php artisan nova:resource Name --no-interaction`
2. Policy under `app/Nova/Policies` with `false` for anything irreversible.
3. Register the policy in `NovaServiceProvider`.
4. Add it to the menu in `NovaServiceProvider::menu()`.
5. A feature test under `tests/Feature/Nova`.
