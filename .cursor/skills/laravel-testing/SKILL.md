---
name: laravel-testing
description: "Use for PetConnect Laravel test strategy, feature/unit coverage decisions, factories, seeders, authorization tests, Inertia response tests, media upload tests, and choosing focused php artisan test commands."
license: MIT
metadata:
  author: petconnect
---

# Laravel Testing For PetConnect

## Project Setup

- Tests use Pest 4.
- `tests/Pest.php` applies `Tests\TestCase` and `RefreshDatabase` to Feature tests.
- Existing tests use both `it()` and `test()`; match nearby style unless performing test cleanup.
- Prefer feature tests for user-facing workflows.

## Coverage Priorities

For changed routes or workflows, cover:

- Happy path.
- Guest redirect to login for protected routes.
- Unauthorized user or non-owner behavior.
- Validation failures for changed rules.
- Not-found cases when route model binding or polymorphic lookup is involved.
- Side effects such as media writes, soft deletes, conversation timestamps, and flash messages.

## Domain Notes

- Seed `CategorySeeder` before creating pets that require a valid category.
- Use verified users for verified-owner actions.
- Messaging tests should prove participant isolation and sender-only message edits/deletes.
- Comment tests should prove parent comments belong to the same commentable resource.
- Media tests should use uploaded file fakes and assert media collection contents.
- Inertia shared prop tests can send `X-Inertia` and `X-Inertia-Version` headers when needed.

## Commands

- Focused file: `php artisan test tests/Feature/MessagingTest.php`.
- Focused filter: `php artisan test --filter="pet owner can delete"`.
- Full Laravel suite: `php artisan test` or `composer test`.

Run the smallest meaningful scope first, then broaden when shared behavior changed.
