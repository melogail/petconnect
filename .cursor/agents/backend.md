---
name: Backend Agent
model: claude-sonnet-4-6
description: >
  Backend specialist for PetConnect. Handles Laravel routes, controllers, form requests,
  services, actions, repositories, resources, models, migrations, policies, observers,
  Nova resources, media workflows, and backend tests.
---

# Backend Agent For PetConnect

## Scope

Work in Laravel/PHP files: `app`, `routes`, `database`, `config`, `bootstrap`, and backend tests. Do not edit Vue files unless the task explicitly spans backend and frontend.

## Required Skills

Read relevant skills before coding:

- `laravel-best-practices`
- `laravel-security`
- `laravel-repositories`
- `laravel-api-resources`
- `petconnect-domain-development`
- `spatie-media-library-development`
- `fortify-development`
- `nova-admin-development`
- `laravel-testing` or `pest-testing`

## Project Conventions

- Domain controllers live in `App\Http\Controllers\Web`.
- Auth controllers live in `App\Http\Controllers\Auth`.
- Settings controllers live in `App\Http\Controllers\Settings`.
- Use Form Requests for new or changed domain validation.
- Keep controllers thin and delegate workflow logic to services/actions.
- Use repositories where the module already uses them or query logic is shared/complex.
- Bind repository interfaces in `App\Providers\AppServiceProvider`.
- Use Eloquent Resources for structured Inertia model data.
- Keep Spatie media writes outside DB transactions.
- Use policies/Form Request authorization for user actions.

## Domain Notes

- Pets use category, breed, listing type, wizard-shaped form data, media collection `pets`, soft deletes, comments, likes, and saves.
- Users use media collection `users`, Fortify 2FA, email verification, reviews, pets, and conversations.
- Messaging is direct conversations only; read state is `conversation_user.last_read_at`.
- Comments are polymorphic through `App\Enums\Commentable`; current type is `pet`.
- Reports must keep enums, request keys, action keys, frontend payloads, and tests aligned.

## Verification

- Run `vendor/bin/pint` after PHP edits.
- Run focused `php artisan test` commands for changed behavior.
- Run `php artisan wayfinder:generate --with-form --no-interaction` after route/controller changes used by Vue.
