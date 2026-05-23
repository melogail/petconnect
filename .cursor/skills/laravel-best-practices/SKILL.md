---
name: laravel-best-practices
description: "Use for PetConnect backend Laravel work: controllers, models, migrations, form requests, policies, services, actions, repositories, resources, Eloquent queries, events/observers, Nova resources, PHP code review, and backend refactors."
license: MIT
metadata:
  author: petconnect
---

# Laravel Best Practices For PetConnect

## First Checks

- Inspect sibling files before choosing an implementation pattern.
- Use Laravel Boost `search-docs` for version-specific Laravel, Inertia, Fortify, Nova, Pest, Wayfinder, or Media Library APIs.
- Keep project behavior consistent with existing routes, resources, policies, and Vue prop shapes.

## Backend Shape

- Domain controllers live in `App\Http\Controllers\Web`.
- Auth controllers live in `App\Http\Controllers\Auth`.
- Settings controllers live in `App\Http\Controllers\Settings`.
- Controllers should stay thin: authorize, validate, call service/action, return Inertia or redirect.
- Use Form Requests for new or changed validation.
- Use Eloquent API Resources for structured Inertia model data when the model/workflow already has a resource.
- Use services for multi-step workflows. Use actions for focused one-step workflows.

## Eloquent

- Prefer relationships and eager loading over manual joins for normal app flows.
- Add local scopes for repeated query constraints.
- Avoid N+1 queries in home feed, profiles, messaging, comments, shared props, and resources.
- Use enum casts for enum-backed columns.
- Keep model casts and relationships aligned with migrations and resources.

## Authorization

- Use policies or Form Request `authorize()` for user actions.
- User-owned models can often extend `App\Policies\Policy`, which requires verified ownership for create/update/delete.
- Messaging uses participant and sender checks; never expose conversations or messages by id alone.

## Media

- Keep Spatie media writes outside database transactions.
- Use `pets` collection for `Pet` media and `users` collection for `User` media.
- Do not attach media to a non-user model unless it has a `user` relationship or `MediaPathGenerator` is updated.

## Verification

- Run `vendor/bin/pint` after PHP edits.
- Run focused Pest tests for changed behavior.
- Regenerate Wayfinder after route/controller changes consumed by Vue.
