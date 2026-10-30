# Backend layers

## Controller → Action → Pipeline

```php
// Controller: authorise, validate, delegate
public function store(StorePetRequest $request, CreatePet $createPet): RedirectResponse
{
    $this->authorize('create', Pet::class);

    $pet = $createPet->handle($request->user(), $request->validated(), $request->file('photos', []));

    return to_route('pets.show', $pet);
}
```

- **Controllers** (`app/Http/Controllers/Web`, `Settings`, `Api/V1`) never contain business rules. Authorization is `$this->authorize()` in the controller, never in a Form Request. API controllers resolve the caller through the `ResolvesRequestUser` concern rather than `$request->user()` directly.
- **Actions** (`app/Actions/{Domain}/`) are single-purpose invokable-style classes with a `handle()` method. Domains in use: Auth, Comments, Contact, Content, Fortify, Likes, Localization, Messaging, Notifications, Options, Pets, Profiles, Reports, Reviews, Tickets, Users.
- **Pipelines** (`app/Pipelines/{Domain}/`) break a multi-step write into ordered steps. An Action runs the pipeline; a second Action is extracted only when it earns its keep. Steps under `Shared/` are reused across create and update flows and type-hint the abstract context; flow-specific steps hint their own context.

The Pet create/update pipeline is the largest. Its shared steps, in order of concern:

| Step | Responsibility |
| --- | --- |
| `ResolveCategoryAndBreed` | Validates the breed belongs to the category |
| `NormalizeBasicAttributes` | Name, gender, birth date, counts, price by listing type |
| `NormalizeLocation` | City/state/country, lat/long |
| `NormalizeHealthData` | Health status, vaccination/medication/allergy arrays, vet details |
| `NormalizeTraits`, `NormalizeAdditionalInfo` | JSON columns |
| `EnsurePhotosAreDecodable` | Rejects images GD cannot decode before anything is stored |
| `AttachGalleryImages` | medialibrary collections for featured and gallery images |
| `RefreshPetWithMedia` | Returns the listing with media eager-loaded for the response |

Generate a new pipeline with `php artisan make:pipeline`. Never name a flow directory the same as a class in `app/Actions/{Domain}`.

## Models and data access

- No repository layer. Query logic lives in Actions and in model scopes.
- `preventLazyLoading()` is enabled and also sees medialibrary N+1s. It is blind on result sets of 0 or 1 row, so fixtures that test eager loading need at least two rows.
- Eager-load constraint closures receive a `Relation`, not a `Builder`.
- Morph type columns hold **aliases** (`Relation::enforceMorphMap`), never class names. Compare to the alias.
- A route-bound child model (comment, message, review) must re-derive its parent's visibility; a soft-deleted or unavailable parent makes the child a 404.
- Pets are addressed by `slug`, assigned in `PetObserver::creating()`. The id never appears in a URL or API path.

## Deletion and cascades

`users` has no soft deletes and several foreign keys cascade off it, but a database cascade fires no Eloquent event and polymorphic children (likes, comments, reviews, reports, media) have no FK to cascade on. Therefore:

- Member account deletion goes through the account-delete Action, which walks every polymorphic relation.
- In Nova, the built-in delete on Users is disabled by policy and replaced by the destructive `DeleteUserAccount` action. The same pattern applies to `PurgePetListing`, `DeleteCommentThread`, `DeleteReview` and `DeleteCategory`.
- medialibrary rows deleted inside a transaction are deferred with `DB::afterCommit` so files are not removed for a rolled-back write.
- Comment subtrees are walked with `ListCommentSubtreeIds`, never a frontier loop.

## Deactivation

A deactivated member (`users.is_active = false`) is signed out by `EnsureAccountIsActive` on the next request, has their Sanctum token revoked on the API group, cannot log in, is hidden from public profiles and listings, and is not addressable by id. Nova exposes `DeactivateUser` / `ReactivateUser` actions.

## Notifications

Six notification classes under `app/Notifications`. The five in-app ones (`ModelLiked`, `ModelCommented`, `ModelReviewed`, `NewMessage`, `ReportFiled`) deliver to `database` and `broadcast` and implement `ShouldQueueAfterCommit`: the job is pushed when the surrounding transaction commits, never before. `VerifyEmailNotification` is a queued mail. Payloads carry a translation key and replacements, rendered client-side in the viewer's locale.

A running worker is therefore required outside the test suite: `composer run dev` starts one locally, Horizon runs them in production.

## Providers

| Provider | Owns |
| --- | --- |
| `AppServiceProvider` | Defaults (strict models, immutable dates, password rules), morph map, media ownership observer, dev command panes |
| `RateLimitServiceProvider` | Every named limiter and the three key helpers (`rateLimitKey`, `unauthenticatedCallerKey`, `passwordConfirmationKey`) |
| `FortifyServiceProvider` | Member auth views and actions |
| `NovaServiceProvider` | Admin gate, menu, resources, Nova routes |
| `HorizonServiceProvider` | Horizon dashboard gate |

## Media

spatie/laravel-medialibrary with project-specific pieces in `app/MediaLibrary/`:

- `MediaPathGenerator` + `OwnerDirectoryResolver`: files are grouped under a per-owner directory name stored on the user.
- `ImageDecodeVerifier`: used by the pipeline to refuse undecodable uploads.
- `TemporaryDirectoryCleaningFileManipulator`: bound over the package's manipulator to clean temp directories on early returns and thrown conversions.
- `ConvertibleImageTypes`: the whitelist of formats that get conversions.

Conversions are queued by default (`QUEUE_CONVERSIONS_BY_DEFAULT=true`); only the `sync` driver, which the test suite uses, runs them inline. Image-storing routes still carry tighter limiters because decoding and storing the upload happens in the request.
