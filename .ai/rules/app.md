---
paths:
  - 'app/**'
---

# App

## Controllers stay thin: Actions and Pipelines hold the logic
Controllers only validate (Form Request), delegate, and return a response — no business logic, no queries, no conditionals beyond guard clauses.

- A single unit of business work goes in a single-purpose Action class in `app/Actions/{Domain}/` with one public `handle()` method.
- A sequence of steps (validate → resolve → persist → notify) goes in `app/Pipelines/{Domain}/{Flow}/`, one class per step, run through `Illuminate\Pipeline\Pipeline` with a typed context object as the passable. Generate steps with `php artisan make:pipeline {Domain}/{Flow}/{Step}`.
- Follow SOLID: one reason to change per class, depend on interfaces for swappable behaviour (gateways, notifiers), constructor-inject dependencies with promoted properties.

## No repository layer: query logic lives in Actions and model scopes
Settled decision: this codebase has **no repository layer**, and the legacy project's `app/Repositories/` and `app/Http/Repository/` are deliberately NOT being ported. Do not introduce repositories, repository interfaces, or a `make:repository` command.

Why the legacy layer was rejected: `app/Http/Repository/PetRepository` was dead code that would fatal (missing import, zero callers); 3 of 4 repositories had interfaces but `CommentRepository` did not; bindings were duplicated and contradictory across `AppServiceProvider` and an unregistered `RepositoryServiceProvider`; `ConversationController` injected the repository interfaces straight into the controller, defeating the abstraction; and most methods were anaemic one-line Eloquent wrappers whose id-taking `update(int $id)` / `delete(int $id)` signatures forced redundant SELECTs.

Where query logic goes instead:
- Query composition for a unit of business work → a single-purpose Action in `app/Actions/{Domain}/` with one public `handle()`.
- Reusable query fragments → **Eloquent model scopes**, the pattern this codebase already uses well (`Pet::available`, `Pet::nearby`, `Conversation::direct` / `forParticipant` / `betweenParticipants`).

## Eager-load constraint closures receive a Relation, not a Builder
In with(['comments' => fn (...) => ...]) the closure is handed the Relation object (MorphMany, HasMany, ...), not an Eloquent\Builder. Type hinting Builder throws a TypeError at runtime, which no static check here catches.

Type these closures fn (Relation $r): Relation. Query methods and model scopes still work through Relation::__call. A closure passed to where() does get a Builder — that one is different.
