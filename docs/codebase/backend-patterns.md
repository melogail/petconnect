# Backend patterns

Short, real examples of each building block. Copy the shape, not the content.

## Enum with labels and options

Every fixed vocabulary is a backed enum. `HasOptions` turns it into the `[{value, label}]` list that selects, Nova filters and `/api/v1/options` all use.

```php
enum ListingType: string
{
    use HasOptions;

    case Adoption = 'adoption';
    case Sale     = 'sale';
    case Mating   = 'mating';

    public function label(): string
    {
        return match ($this) {
            self::Adoption => 'Adoption',
            self::Sale     => 'Sale',
            self::Mating   => 'Mating',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Adoption => 'primary',
            self::Sale     => 'success',
            self::Mating   => 'warning',
        };
    }
}
```

Usage: `ListingType::options()` in a controller prop, `ListingType::from($value)` after validation, `$pet->listing_type->label()` in a resource.

## Domain exception

Exceptions live under `app/Exceptions/{Domain}` and are thrown by pipeline steps or Actions. When the abort really is a bad field, extend `ValidationException` so Laravel renders a 422 or a redirect-back for free:

```php
abstract class PetTaxonomyNotFound extends ValidationException
{
    protected array $diagnostics = [];

    protected static function forField(string $field, string $message, array $diagnostics): static
    {
        $exception              = static::withMessages([$field => $message]);
        $exception->diagnostics = $diagnostics;

        return $exception;
    }
}
```

`CategoryNotFound::withId($id)` and `BreedNotFound::withId($id, $categoryId)` extend it. Anything that is not a field problem gets a plain exception class and its own render mapping.

## Pipeline step

A step is one class, one method, one concern. It type-hints its context, returns `$next($context)`, and never reads `config()` or the request.

```php
class NormalizeLocation
{
    public function handle(PetAttributeContext $context, Closure $next): mixed
    {
        $location = $context->input('location', []);

        $context->set('city', $location['city'] ?? null);
        $context->set('state', $location['state'] ?? null);
        // ...

        return $next($context);
    }
}
```

Scaffold one with:

```bash
php artisan make:pipeline Pets/Archive/MarkUnavailable
```

Shared steps under `Shared/` hint the abstract context (`PetAttributeContext`); flow-specific steps hint their own (`CreatePetContext`).

## Action without a pipeline

Most Actions are plain. `RecordPetView` deduplicates views per visitor and bumps the counter; `ToggleLike` flips a like. Shape:

```php
class RecordPetView
{
    public function handle(Pet $pet, ?User $viewer, string $visitorKey): void
    {
        // dedupe window from config, resolved here and not in a step
        // ...
        $pet->increment('views');
    }
}
```

Inject Actions into controllers by type; the container builds them.

## Model concerns

Reusable relation behaviour is a trait under `app/Concerns`. `HasLikes` gives a model `likes()`, `isLikedBy()` and `withLikedBy()`; `Pet`, `Comment` and `User` all use it.

```php
class Pet extends Model implements Commentable, Likeable, HasMedia
{
    use HasComments, HasFactory, HasLikes, HasSaves, InteractsWithMedia, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

Contracts under `app/Contracts` (`Likeable`, `Commentable`, `Reportable`, `Reviewable`) are what the polymorphic Actions type-hint against.

## Morph aliases

```php
Relation::enforceMorphMap([
    'comment' => Comment::class,
    'pet'     => Pet::class,
    'review'  => Review::class,
    'user'    => User::class,
    // ...
]);
```

Compare `likeable_type` to `'pet'`, never to `Pet::class`. `ResolvesMorphTarget` maps a route segment like `comments/{commentable_type}/{commentable_id}` to the model through the `Commentable` enum.

## Rate limiter

Declared once in `RateLimitServiceProvider`, attached by name in routes. Three key helpers decide who a hit is counted against:

```php
protected function rateLimitKey(Request $request): string
{
    return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
}

protected function unauthenticatedCallerKey(Request $request): string
{
    return (string) $request->ip();
}
```

Use `rateLimitKey()` for authenticated writes and `unauthenticatedCallerKey()` for guest routes, so a guest route's IP keying is a decision and not a fallback.

## Queued notification

In-app notifications implement `ShouldQueueAfterCommit`, so the job is pushed only after the surrounding transaction commits and a Redis worker never reads an uncommitted row.

```php
class ModelLikedNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public Like $like) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        $this->like->loadMissing(['user', 'likeable']);

        return [
            'like_id'         => $this->like->id,
            'message_key'     => 'notifications.liked_pet',
            'message_replace' => ['name' => $this->like->user?->name],
            // ...
        ];
    }
}
```

The payload carries a translation key and replacements, not a sentence, so the client renders it in the viewer's locale.

## Observer

Lifecycle rules that must hold for every writer, including Nova and seeders, go in an observer rather than an Action:

```php
public function updated(Pet $pet): void
{
    if ($pet->quantity > 1 && $pet->male_count === 0 && $pet->female_count === 0) {
        $pet->status = PetStatus::Unavailable;
        $pet->saveQuietly();
    }
}
```

Observers are registered with `#[ObservedBy]` on the model or in `AppServiceProvider::boot()`.

## Deleting with polymorphic children

Database cascades skip Eloquent and cannot reach morph children. Deletion is therefore a pipeline that names every child type, run inside one transaction:

```php
return DB::transaction(fn (): bool => $this->pipeline
    ->send($context)
    ->through([
        CollectAccountContent::class,
        DeleteContentReports::class,
        DeleteContentLikes::class,
        DeleteContentSaves::class,
        DeleteContentComments::class,
        DeleteReviewsAboutAccount::class,
        PurgeOwnedListings::class,
        DeleteAccountNotifications::class,
        DeleteAccountRecord::class,
    ])
    ->then(fn (): bool => true));
```

When you add a new polymorphic child, add a step here and in `PurgePet`.
