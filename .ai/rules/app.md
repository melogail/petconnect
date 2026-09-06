---
paths:
  - 'app/**'
---

# App

> **Widening a signature can lose a capability the same way deleting code does** — here the shape
> is a parameter gaining a default or a `?` type, so every existing caller still compiles and the
> loss only shows at a caller that never supplied the value. The review check, its
> operationalisation and the measured instance live in `.ai/rules/general.md`, "Review what a
> change REMOVED — and what it merely made optional". This is a pointer, not a copy.

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

So: any test that is meant to protect an eager load must seed at least two rows of the model being iterated, and must not trust the exception alone. This applies to every fixture that carries a `->with(...)` claim.

**What to assert depends on which half of the load you are protecting, and the count only covers one of them.**

- *Half-miss* — the relation is loaded but a nested one is not (`with('user')` where the payload reaches `user.media`). The guardrail fires, or the query count goes **up**. A count assertion is the right guard here, and this is the case the paragraph above is about.
- *Complete miss* — nothing eager loads the relation at all and the payload reads it through `whenLoaded()`. The key is silently dropped, no relation is ever touched, so no exception fires and **the query count goes DOWN**. Measured twice: `ListReviews` 5→3 and `BuildInbox` 7→2. What a count does about that depends on how it is written, and the earlier wording here ("a count assertion agrees with the regression") was only true of a *ceiling*: `toBeLessThanOrEqual(7)` passes on 2, so a ceiling does agree with the regression. **Every pin in this suite is an equality**, and `toBe(7)` fails on 2 — so the equality catches the complete miss, which is precisely why they are equalities. Keep asserting the key is present in the serialised payload as well: the key assertion states the intent, and it is the thing that still holds if a pin is ever loosened to a ceiling. See .ai/rules/resources.md and .ai/rules/tests.md.

## Every pinned query count in this codebase is session-blind
`phpunit.xml` sets `SESSION_DRIVER=array` and `CACHE_STORE=array`, so every figure in an Action docblock and every count assertion in the suite excludes the session and cache round trips a real request pays. `.env` runs `SESSION_DRIVER=database` and `CACHE_STORE=database`: measured on the same home feed request and the same fixture, **9 queries with the array driver, 12 for a guest and 11 authenticated with the database one**, the difference being `sessions` reads and writes.

Two things follow. A docblock that says "this Action is a flat 5 queries" is Action-scoped and correct; do not read it as the cost of the page. And the 2-3 query per-request tax is charged on *every* route in the application, which is larger than several of the eager-load fixes recorded in this file were individually worth — Redis is already configured (`REDIS_CLIENT=phpredis`) and unused, and `.env.example` now documents redis for both drivers in production. `.env` is a local dev file and was deliberately not changed; the production configuration is a question for whoever owns the deploy.

## Morph type columns hold aliases: never compare them to a class name
`Relation::enforceMorphMap()` is on (AppServiceProvider::configureMorphMap), so every `*_type` column stores an alias — `pet`, not `App\Models\Pet`. Any comparison against a class name matches zero rows and *passes silently*: `Rule::exists('comments','id')->where(fn ($q) => $q->where('commentable_type', $type->modelClass()))` validates nothing, and a raw `where('commentable_type', Pet::class)` filters nothing away.

Nothing catches it — no exception, no static check, and a test written against the same wrong assumption agrees with it. Where you must name the type in a query, go through `Relation::getMorphAlias(Model::class)`; `CommentFactory` and `PersistComment` are the worked examples.

Better still, do not build the filter at all: read through the relation (`$commentable->rootComments()`, `$comment->replies()`), which fills the morph columns from the model. `ListCommentThread` was rewritten to do that and the read path now constructs no morph value by hand.

Best of all, keep morph existence checks out of Form Requests. A request cannot see the resolved target, so it has to rebuild the morph filter from the URL — the exact shape above. Whether a parent comment is on this thread is decided in `PublishComment\ValidateParentBelongsToCommentable`, in one query, against the model the flow already holds.

(The legacy app had the opposite problem and is not evidence for this one: it registered no morph map at all, so its class-name comparisons matched correctly. Do not repeat the claim that they were broken.)

## A route-bound child model must re-derive its parent's visibility
**Principle.** A URL that names only the child — `comments/{comment}/replies`, `messages/{message}/pin` — carries no evidence that the parent is still visible, and route model binding will happily hand you a child of a hidden parent. It shipped twice: with a pet trashed, `comments.index` and the pet page 404'd while `comments.replies` still returned the whole discussion (text plus each author's name, username and location) at a guessable sequential id and `comments.like` still wrote likes; the same shape left a soft-deleted conversation's messages editable, pinnable and deletable. Decide it once on the child, not per route.

**The mechanism depends on how the parent is hidden. Pick by that, not by habit.**

- Parent hidden by a **global scope** (`SoftDeletes` on `Pet`, on `Conversation`) → a `resolveRouteBinding()` override on the child is the right tool. `loadMissing('parent')` and return null when the relation is null; the scope has already done the deciding, so a null relation is a *complete* answer. `Comment` and `Message` both do this, and returning null makes `ImplicitRouteBinding` raise `ModelNotFoundException` — the same 404 the parent's own page gives.
- Parent hidden by **participation or ownership** → the binding override cannot see it and must not be trusted with it. `$message->conversation` is non-null for a conversation the viewer was never a participant in, so the null-check answers nothing there. That dimension belongs in a **policy**: `MessagePolicy::update/delete/pin` go through `Conversation::hasParticipant()`. The two are complementary, not alternatives — `Message` needs both, because a conversation can be hidden either way.

**The override is not always reached.** `ImplicitRouteBinding::resolveForRoute()` picks one of four resolvers and only one of them is your override:

1. `$child->resolveRouteBinding($value, $field)` — the default, and the only path an override on the child intercepts.
2. `$child->resolveSoftDeletableRouteBinding()` — taken when the route calls `->withTrashed()` and the child `isSoftDeletable()`. It calls `resolveRouteBindingQuery(...)->withTrashed()->first()` directly, so the override is skipped. **Live for `Message`**, which soft-deletes.
3. `$parent->resolveChildRouteBinding()` and 4. `$parent->resolveSoftDeletableChildRouteBinding()` — taken whenever the route has a parent parameter in the URI *and* (`->scopeBindings()` is on *or* the child has a binding field), unless `->withoutScopedBindings()`. Both resolve through the **parent's** `resolveChildRouteBindingQuery()` and never touch the child, so the override is dead code.

Today no route hits 2–4: `messages/{message}` and `comments/{comment}` are top-level, nothing calls `withTrashed()`, and the only nested routes bind `{conversation}` alone. Keep it that way. If a scoped or trashed variant is ever needed, the visibility check has to move — to the parent's `resolveChildRouteBindingQuery()`, or to a policy — because the child override will silently stop running. Nothing fails loudly when it does.

Do not put the check in the Action: `ListCommentReplies` is handed a comment the binding already vetted, and repeating it there would leave `like`/`update`/`destroy` uncovered and break the Action's query-count ceiling.

**On the MorphTo:** do not repeat the claim that "`whereHas` cannot reach through a `MorphTo`" — it was the reason recorded here and in `Comment` for two phases, and it is false. `whereHasMorph()` exists and does reach through one. Constraining the binding query is declined, not unavailable: `whereHasMorph('commentable', '*')` resolves the wildcard with its own `distinct()->pluck()` over the whole comments table (so it buys no query back, and only finds morph types that already have rows), and an explicit type list hardcodes the commentable whitelist into the model where nothing keeps it in step with the morph map. Loading the relation costs the same one query and leaves the parent on the model the controller receives.

## What deactivation means: `users.is_active`, settled
`is_active` used to gate exactly one thing — message delivery, via `User::acceptsMessagesFrom()` — so a "deactivated" account could still sign in, publish a listing, comment, like and review. That was recorded here as a gap for a later phase to settle once. Phase 2e settled it.

**The predicate lives in one place: `User::isActive()`.** Nothing reads the `is_active` column directly any more. Deactivated now means, in full:

1. **No session.** `Http\Middleware\EnsureAccountIsActive` runs in the `web` group (appended, so ahead of `auth` and `verified` and ahead of `HandleInertiaRequests`). Any authenticated request from a deactivated account is logged out, the session invalidated and the CSRF token regenerated, then redirected to `login` with a `status`. It adds no query, though not for the obvious reason: nothing in the default `web` group resolves the user, so this middleware is the **first** thing to call `$request->user()` and is what triggers the guard's lookup. That lookup is memoized and `HandleInertiaRequests` shares the same user into every response, so the query was always going to happen and this only moves it earlier. `is_active` is then a column already on the row.
2. **No usable sign-in.** Fortify's credential check is deliberately *not* overridden. `Fortify::authenticateUsing()` replaces the guard's whole credential check, so bolting an application rule onto it means reimplementing `fortify.lowercase_usernames`, `Fortify::username()` and rehash-on-login — and it still would not cover the passkey route, which does not go through it. A deactivated login succeeds and the next request ends it. One check, every way in.
3. **No public profile.** `UserPolicy::view` returns `$profile->isActive()`, for everyone. There is no owner carve-out, because (1) means a deactivated account can never be the viewer. Verified by request, and the mechanism is not what it looks like: the policy is never asked at all — the middleware ends the session first, so a browser request is redirected to `login` and a JSON one is aborted 403, and the controller that would call `authorize()` never runs. `Gate::allows('view', $deactivated)` asked directly still returns false, which is what the policy test pins.
4. **No incoming messages.** `User::acceptsMessagesFrom()`, unchanged.

What it deliberately does **not** mean: existing listings, comments and reviews stay published. Retiring them would need every listing query to join `users`, on the busiest read path in the application, for a flag almost no row carries — and retiring content is a moderation action with its own audit trail, which belongs on the Nova resource in Phase 3.

`is_active` is absent from `User`'s `#[Fillable]` and from every Form Request, so no request bag can flip it. Deactivation is set in Nova on the `admins` guard. Self-service deactivation, if it is ever wanted, is its own flow with its own confirmation — do not add a checkbox to the profile form.

## Correction: "What deactivation means" now has a fifth clause — not addressable by id
The four-point list above ("No session / no usable sign-in / no public profile / no incoming messages") was incomplete, and point 3 attributed the profile block to `UserPolicy::view` alone. Phase 6 measured the gap: the reviews vertical resolves a user through `App\Enums\Reviewable::findVisibleOrFail()`, which asks the *model*, not the policy — so `GET /reviews/user/{id}` returned the full list and `POST /reviews/user/{id}` wrote a review about, and notified, a deactivated account.

Read point 3 as: **not addressable by id, anywhere.** `User::resolveRouteBinding()` refuses a deactivated account, so `profile.show` and `profile.like` are a **404** (not the 403 this file records) and every `findVisibleOrFail()` caller inherits it. `UserPolicy::view` still refuses the profile and still answers `Gate::allows()` directly; it is no longer the only enforcement point or the one a URL hits first. Full reasoning in .ai/rules/models.md.

Unchanged: existing listings, comments and the reviews the account *wrote about other people* stay published. Only the endpoint that addresses the account itself stopped answering.

## whereHas on a one-of-many relation scans the whole child table
`latestOfMany()` / `oldestOfMany()` / `ofMany()` relations are cheap to *eager load* and expensive to *filter by*. An existence check on one compiles to `exists (... inner join (subquery) ...)` where the subquery groups the entire child table by the foreign key, uncorrelated — nothing from the outer query narrows it, so no index and no statistic changes what it reads.

Measured on dev MySQL 8.0.46 (15 conversations, 277 rows in `messages`), `Conversation::lastMessage()` = `hasOne(Message::class)->latestOfMany('created_at')`, for one user with 5 conversations. `whereHas('lastMessage', ...)`:

```
PRIMARY  conversations      type=ALL     key=NULL                                       rows=15
PRIMARY  conversation_user  type=eq_ref  key=conversation_user_conversation_id_user_id_unique  rows=1
PRIMARY  <derived3>         type=ref     key=<auto_key1>                                rows=2
PRIMARY  messages           type=eq_ref  key=PRIMARY                                    rows=1
DERIVED  <derived4>         type=ALL     key=NULL                                       rows=27
DERIVED  messages           type=ref     key=messages_conversation_id_created_at_index  rows=1
DERIVED  messages           type=index   key=messages_conversation_id_created_at_index  rows=277   <- every row in the table
```

The same predicate written as a correlated subquery on the `$column` side of `where()` (`where(Message::query()->select('sender_id')->whereColumn(...)->orderByDesc(...)->limit(1), '!=', $id)`):

```
PRIMARY            conversations      type=ALL     key=NULL                                       rows=15  Using where
PRIMARY            conversation_user  type=eq_ref  key=conversation_user_conversation_id_user_id_unique  rows=1
DEPENDENT SUBQUERY messages           type=ref     key=messages_conversation_id_created_at_index  rows=18  Using where; Using filesort
```

Same answer, one index lookup per candidate row instead of a full-table materialisation. `Actions\Messaging\CountUnreadConversations` is the worked example and carries the full reasoning.

Two caveats, both measured: reading through `Model::query()` inside the subquery keeps the child's global scopes (SoftDeletes) — a raw string does not, and that clause is load-bearing. And do not read the *outer* half of either plan as a property: at 15 rows MySQL drives from a full scan of `conversations` in both, the opposite of the plan `BuildInbox` records for the sibling query, and the optimiser will likely flip as the table grows.

This is filed under `app/**` on purpose. The mistake is written wherever queries are composed — `app/Actions/**`, `app/Pipelines/**` and model scopes in `app/Models/**` — not where the fixed Action happens to live (see "A rule's glob must cover where the mistake gets made").
