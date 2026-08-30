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

## preventLazyLoading is on, and it now sees medialibrary N+1s too
`AppServiceProvider::configureDefaults()` calls `Model::preventLazyLoading(! isProduction())`. The violation callback lets a lazy load through in two cases and throws otherwise:

1. The model is not persisted or was just created — the framework's own early return, which a custom callback replaces and therefore has to restore.
2. The request is being served by Nova, which lazy loads inside its own field resolution. That check is `Laravel\Nova\Util::isNovaRequest($request)`, delegated to the package on purpose. Do not re-derive it from `config('nova.path')`: Nova registers 110 routes and only 32 sit under `nova/`. 76 are `nova-api/*` — every resource index, detail, relatable and action field resolution, i.e. all the code that actually lazy loads — and 2 are `nova-vendor/*`. A `$request->is($path, $path.'/*')` check returned false for all 78, so the scope-out covered the SPA shell and nothing that renders a field.

**The medialibrary blind spot is closed.** `config('media-library.force_lazy_loading')` used to be the package default `true`, which makes `InteractsWithMedia::loadMedia()` call `loadMissing('media')` first — an explicit eager load the guardrail permits — so `getFirstMediaUrl()` / `getMedia()` on an unloaded model issued a silent query per model instead of throwing. That is how the home feed reached 54 media queries on a 12-card page and the category tree 9, both found by counting queries by hand. It is now `env('FORCE_MEDIA_LIBRARY_LAZY_LOADING', env('APP_ENV') === 'production')`, so outside production the guardrail throws on them. See .ai/rules/config.md.

Any resource that calls `getFirstMediaUrl()` or `featuredPhotoUrl()` still means the caller must eager load that model's `media` explicitly — `user.media`, `category.media`, `comments.user.media`. The difference is that forgetting is now an exception in dev and test rather than a silent query, and the fix is always the eager load, never turning the flag back on.

## preventLazyLoading is blind on result sets of 0 or 1 row
Eloquent\Builder::hydrate() only sets `$model->preventsLazyLoading` when `count($items) > 1`. A model fetched with first(), find(), findOrFail(), sole(), or any get() that returned a single row has the guard OFF and lazy loads silently — `Category::query()->first()->media` issues a query and says nothing, while `Category::query()->take(3)->get()[0]->media` throws.

Consequence for fixtures: a render test with zero or one row proves nothing about lazy loading. Deleting an eager load can leave the whole suite green while the N+1 comes back — `ListPetCategories`'s `media` load is only observable with 2+ categories, and route-model-bound `show`/`edit` pages (one model, from `find`) are unguarded by construction.

So: any test that is meant to protect an eager load must seed at least two rows of the model being iterated, and assert on the query count rather than trusting the exception. This applies to every fixture that carries a `->with(...)` claim.
