---
name: pest-testing
description: "Use whenever PetConnect tests are written, edited, fixed, reviewed, or run with Pest, including Feature tests, Unit tests, factories, datasets, validation cases, Inertia assertions, policies, services, actions, media uploads, and regression coverage."
license: MIT
metadata:
  author: petconnect
---

# Pest Testing For PetConnect

## Style

- Match the nearby file's use of `it()` or `test()`.
- Use descriptive names that state behavior.
- Use route names instead of literal paths.
- Use factories and seeders rather than hand-built records.
- Use specific response assertions: `assertRedirect()`, `assertForbidden()`, `assertSessionHasErrors()`, `assertOk()`, `assertNotFound()`.

## Useful Patterns

```php
it('redirects guests from the conversations index', function () {
    $this->get(route('conversations.index'))->assertRedirect(route('login'));
});
```

```php
it('prevents non-participants from viewing a conversation', function () {
    $conversation = Conversation::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('conversations.show', $conversation))
        ->assertForbidden();
});
```

## Data Setup

- Use `User::factory()->create(['email_verified_at' => now()])` when verified behavior matters.
- Seed `CategorySeeder` when a pet factory needs a valid category/breed setup.
- Use `Storage::fake()` and uploaded file fakes for media upload tests.
- Use `Notification::fake()`, `Mail::fake()`, `Event::fake()`, and `Queue::fake()` before the action being tested.

## Inertia

- Assert component names and critical props for pages when useful.
- For shared props, use Inertia request headers and assert JSON props when following existing tests.

## Commands

- `php artisan test --filter=MessagingTest`
- `php artisan test tests/Feature/CommentStoreTest.php`
- `php artisan test`
