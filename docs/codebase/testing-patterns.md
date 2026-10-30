# Testing patterns

Pest 5, feature tests first. A test says what the behaviour is for; the docblock above it says why the assertion is shaped the way it is.

## HTTP boundary

Drive the route, assert the response and the database. `tests/Feature/Http/DocsSiteTest.php`:

```php
test('a guest is sent to the admin login and told not to index', function () {
    $this->get('http://'.config('docs.domain').'/')
        ->assertRedirect(route('nova.pages.login'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('an admin reads the build with clean urls and noindex headers', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')
        ->get('http://'.config('docs.domain').'/guide/api')
        ->assertOk()
        ->assertSee('API');
});
```

Members: `actingAs($user)`. Staff: `actingAs($admin, 'admin')`. A member on an admin route is a guest.

## Action level

Pipelines are tested through the Action that runs them. `tests/Feature/Actions/Pets/CreatePetTest.php`:

```php
function createPetData(Category $category, array $overrides = []): array
{
    return [
        'name'         => 'Luna',
        'category_id'  => $category->getKey(),
        'listing_type' => ListingType::Adoption->value,
        'location'     => ['city' => 'Cairo', 'state' => 'Cairo', 'country' => 'Egypt'],
        ...$overrides,
    ];
}

test('normalizes the health repeaters to their documented shapes', function () {
    $category = Category::factory()->create();

    $pet = app(CreatePet::class)->handle(User::factory()->create(), createPetData($category, [
        'health' => ['vaccinations' => [['name' => 'Rabies', 'date' => '2024-01-15']]],
    ]));

    expect($pet->vaccinations)->toBe([['name' => 'Rabies', 'date' => '2024-01-15']]);
});

test('rejects a breed from another category', function () {
    // ...
})->throws(BreedNotFound::class);
```

## Queue and notifications

Fake the queue to prove work left the request. `tests/Feature/Notifications/QueuedNotificationsTest.php`:

```php
test('liking a listing queues the owner notification instead of sending it inline', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $pet   = Pet::factory()->for($owner)->create();

    $this->actingAs(User::factory()->create())
        ->post(route('pets.like', $pet))
        ->assertRedirect();

    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job): bool =>
        $job->notification instanceof ModelLikedNotification
        && $job->notifiables->contains($owner));
});
```

`Notification::fake()` is for "was it sent to whom"; `Queue::fake()` is for "did it go through the queue".

## Datasets

One assertion, several subjects. Break one subject afterwards and confirm exactly one case fails, named after it.

```php
test('every in-app notification is queued after commit', function (string $class) {
    expect(is_subclass_of($class, ShouldQueueAfterCommit::class))->toBeTrue();
})->with([
    ModelLikedNotification::class,
    ModelCommentedNotification::class,
    NewMessageNotification::class,
]);
```

## Eager loading

Pin the query count **and** assert the key exists, because a dropped eager load lowers the count and leaves `whenLoaded()` keys silently absent:

```php
$payload = PetCardResource::make($pet)->response()->getContent();

expect(json_decode($payload, true)['data'])->toHaveKey('user');
```

## Media

```php
UploadedFile::fake()->image('photo.jpg');   // real bytes, conversion runs
UploadedFile::fake()->create('photo.jpg');  // 0 bytes, conversion exits early
```

## Running

```bash
php artisan test tests/Feature/Actions/Pets --compact
php artisan test --compact --filter='queues the owner'
php artisan test --compact                     # whole suite, about 95 s
```

Full suite on 2026-10-26: 1,153 tests, 1,149 passed, 4 skipped. PHPStan level 7: 0 errors.
