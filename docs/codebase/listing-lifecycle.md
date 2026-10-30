# A listing, end to end

The best way to learn the codebase is to follow one write through every layer. This page follows `POST /pets`, the publish step of the listing wizard, with the real code at each hop. Docblocks are trimmed; the files hold the full reasoning.

```
Vue wizard ─▶ routes/web.php ─▶ PetController@store ─▶ StorePetRequest
          ─▶ CreatePet (Action) ─▶ Pets/Create pipeline ─▶ PetObserver ─▶ PetDetailResource ─▶ pets/Show.vue
```

## 1. The route

`routes/web.php`. Verified members only, slug-bound, and every write names a limiter.

```php
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('pets/create', [PetController::class, 'create'])->name('pets.create');
    Route::post('pets', [PetController::class, 'store'])
        ->middleware('throttle:pet-listings')
        ->name('pets.store');

    Route::where(['pet' => Pet::ROUTE_KEY_PATTERN])->group(function (): void {
        Route::put('pets/{pet}', [PetController::class, 'update'])
            ->middleware('throttle:pet-listing-edits')
            ->name('pets.update');
        Route::patch('pets/{pet}/status', [PetController::class, 'toggleStatus'])
            ->middleware('throttle:content-edits')
            ->name('pets.status.toggle');
        Route::post('pets/{pet}/like', [PetController::class, 'toggleLike'])
            ->middleware('throttle:pet-likes')
            ->name('pets.like');
    });
});
```

The `pet-listings` limiter is declared in `app/Providers/RateLimitServiceProvider.php`:

```php
RateLimiter::for('pet-listings', fn (Request $request): array => [
    Limit::perMinute(5)->by('minute:'.$this->rateLimitKey($request)),
    Limit::perHour(30)->by('hour:'.$this->rateLimitKey($request)),
]);
```

## 2. The controller

`app/Http/Controllers/Web/PetController.php`. Authorise, take validated input, call the Action, flash, redirect. Nothing else.

```php
public function store(StorePetRequest $request, CreatePet $createPet): RedirectResponse
{
    $this->authorize('create', Pet::class);

    $pet = $createPet->handle(
        owner: $this->actor($request),
        data: $request->validated(),
        featuredImage: $request->featuredImage(),
        galleryImages: $request->galleryImages(),
    );

    Inertia::flash('toast', ['type' => 'success', 'message' => __('Listing published.')]);

    return to_route('pets.show', $pet);
}
```

The API twin in `app/Http/Controllers/Api/V1/PetController.php` is the same five lines with a JSON response instead of a redirect. Both resolve the caller through `ResolvesRequestUser`:

```php
protected function actor(Request $request): User
{
    $user = $request->user();

    if (! $user instanceof User) {
        throw new AuthenticationException;
    }

    return $user;
}
```

## 3. Validation

`app/Http/Requests/Pet/StorePetRequest.php` is two lines because the rules live in a shared concern that the update request, the resource and the options endpoint also read.

```php
class StorePetRequest extends FormRequest
{
    use PetValidationRules;

    public function rules(): array
    {
        return $this->petRules(requiresFeaturedImage: true);
    }
}
```

No `authorize()` here. Authorization is the controller's job.

## 4. The policy

`app/Policies/PetPolicy.php`. Verified account to create; verified and owner to change.

```php
public function create(User $user): bool
{
    return $user->isVerified();
}

public function update(User $user, Pet $pet): bool
{
    return $user->isVerified() && $user->getKey() === $pet->user_id;
}
```

## 5. The Action

`app/Actions/Pets/CreatePet.php` builds a typed context and runs an explicit step list. The Action is the only place that knows the order.

```php
class CreatePet
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(User $owner, array $data, ?UploadedFile $featuredImage = null, array $galleryImages = []): Pet
    {
        $context = new CreatePetContext($owner, $data, $featuredImage, $galleryImages);

        return $this->pipeline
            ->send($context)
            ->through([
                EnsurePhotosAreDecodable::class,
                ResolveCategoryAndBreed::class,
                NormalizeBasicAttributes::class,
                NormalizeLocation::class,
                NormalizeHealthData::class,
                NormalizeTraits::class,
                NormalizeAdditionalInfo::class,
                PersistPet::class,
                AttachFeaturedImage::class,
                AttachGalleryImages::class,
                RefreshPetWithMedia::class,
            ])
            ->then(fn (CreatePetContext $completed): Pet => $completed->pet());
    }
}
```

## 6. The context and the steps

The context carries input in, state across steps, and the result out. `app/Pipelines/Pets/Create/CreatePetContext.php` adds the owner to the shared attribute context that the update flow also uses:

```php
class CreatePetContext extends PetAttributeContext
{
    public function __construct(
        public readonly User $owner,
        array $data,
        ?UploadedFile $featuredImage = null,
        array $galleryImages = [],
    ) {
        parent::__construct($data, $featuredImage, $galleryImages);
    }
}
```

A shared step reads and writes the context, throws a domain exception to abort, and never touches HTTP or `config()`. `app/Pipelines/Pets/Shared/ResolveCategoryAndBreed.php`:

```php
public function handle(PetAttributeContext $context, Closure $next): mixed
{
    $categoryId = (int) $context->input('category_id');
    $category   = Category::query()->find($categoryId);

    if ($category === null) {
        throw CategoryNotFound::withId($categoryId);
    }

    $context->set('category_id', $category->getKey());

    // ... breed must belong to the category, else BreedNotFound
    return $next($context);
}
```

The flow-specific step writes the row. `app/Pipelines/Pets/Create/PersistPet.php`:

```php
public function handle(CreatePetContext $context, Closure $next): mixed
{
    $pet = DB::transaction(fn (): Pet => Pet::create([
        ...$context->attributes(),
        'user_id' => $context->owner->getKey(),
    ]));

    $context->setPet($pet);

    return $next($context);
}
```

## 7. The observer

`PetObserver::creating()` gives the row its public key before it is saved. Nothing else in the app knows how a slug is made.

```php
public function creating(Pet $pet): void
{
    if (filled($pet->slug)) {
        return;
    }

    $prefix    = Str::slug($pet->breed->name ?? $pet->category->name ?? '');
    $pet->slug = ($prefix === '' ? 'pet' : $prefix).'-'.Str::lower(Str::random(8));
}
```

## 8. The response

The redirect lands on `PetController@show`, which renders an Inertia page from a resource:

```php
return Inertia::render('pets/Show', [
    'pet'              => PetDetailResource::make($loadPetDetail->handle($pet, $this->viewer($request))),
    'reportCategories' => ReportCategory::options(),
    'reportReasons'    => ReportReason::options(),
    'commentBounds'    => $this->commentBounds(),
]);
```

Resources only emit relations that were eager-loaded, so a missing eager load shows up as a missing key rather than an N+1. From `PetCardResource`:

```php
'category' => PetCategoryOptionResource::make($this->whenLoaded('category')),
'breed'    => PetBreedOptionResource::make($this->whenLoaded('breed')),
'user'     => PetOwnerResource::make($this->whenLoaded('user')),
'is_owner' => $request->user()?->getKey() === $this->user_id,
'image'    => $this->featuredPhotoUrl('thumb'),
```

## 9. The page

`resources/js/pages/pets/Show.vue` receives the props typed, and links back through Wayfinder rather than a URL string:

```ts
import { home } from '@/routes';
import type { CommentBounds, PetDetail, ReportCategory, ReportReason, SelectOption } from '@/types';

const { pet } = defineProps<{
    pet: PetDetail;
    reportCategories: SelectOption<ReportCategory>[];
    reportReasons: SelectOption<ReportReason>[];
    commentBounds: CommentBounds;
}>();
```

## What to copy when you add a feature

1. Route with a named limiter on every write.
2. Thin controller: `authorize`, Form Request, Action, response.
3. Rules in a Form Request; shared rule bags only when the API and web both validate the same shape.
4. One Action per use case. A pipeline only when the write has several steps worth testing on their own.
5. Domain exceptions under `app/Exceptions/{Domain}`.
6. Resources with `whenLoaded()` for every relation.
7. A Vue page under `resources/js/pages`, typed props in `resources/js/types`, routes from `@/routes` or `@/actions`.
8. Tests at the HTTP boundary and, for pipelines, at the Action.
