---
name: laravel-repositories
description: "Use when PetConnect work touches repository interfaces or implementations, shared Eloquent queries, service persistence dependencies, conversation/message/pet/comment repositories, or repository bindings in service providers."
license: MIT
metadata:
  author: petconnect
---

# Laravel Repositories For PetConnect

## Current Reality

PetConnect uses repositories for several domain flows, but the codebase does not enforce repositories for every Eloquent query. Use repositories where they already exist or when query logic is shared, complex, or service-owned.

Current repository areas include pets, conversations, messages, and comments.

## Conventions

- Interfaces live in `app/Repositories/Interfaces` when an interface exists.
- Concrete Eloquent repositories live in `app/Repositories/Eloquent`.
- Bind repository interfaces in `App\Providers\AppServiceProvider`; `RepositoryServiceProvider` is not currently registered in `bootstrap/providers.php`.
- Services should depend on interfaces when an interface exists.
- Repositories should return Eloquent models, collections, paginators, or booleans; frontend shaping belongs in Resources.
- Business decisions belong in Services or Actions, not repositories.

## Good Uses

- Reusing inbox and direct conversation queries.
- Encapsulating pet list/find/create/update/delete queries.
- Centralizing message pagination or creation for a conversation.
- Adding query methods that need consistent eager loading.

## Avoid

- Introducing a repository solely to move a single straightforward line from an existing controller when the surrounding module does not use repositories.
- Putting authorization, notifications, media writes, or request validation in repositories.
- Binding interfaces in an unregistered provider.

## Verification

- Add focused tests when repository behavior affects visible workflows.
- Watch for N+1 regressions when changing eager loads.
