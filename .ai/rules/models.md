---
paths:
  - 'app/Models/**'
---

# Models

## Enforce a morph map before any data is seeded
The morph map lives in `AppServiceProvider::configureMorphMap()` via `Relation::enforceMorphMap([...])`, called from `boot()`. Register every morphable model there before any data is seeded, and keep it updated as polymorphic models are added.

Five polymorphic relations (`likes`, `saves`, `comments`, `reviews`, `reports`) plus `media.model_type` and `notifications.notifiable_type` store these aliases. The legacy app stored fully-qualified class names and called `enforceMorphMap()` nowhere, so any namespace or class rename orphaned every polymorphic row. Fixing it after seeding means a data migration, so the map has to land first. `App\MediaLibrary\MediaPathGenerator` also builds stored file paths out of `media.model_type`, so an alias here is effectively permanent once files exist.

(Kept in sync with the copy in the other file: the trap bites both the model author and the person editing AppServiceProvider, so .ai/rules/models.md and .ai/rules/providers.md carry the same text. Edit both together.)

## Every Nova resource model must be in the morph map (ActionEvent)
Nova's `ActionEvent` writes `actionable_type`, `target_type` and `model_type` as morph values. Because `Relation::enforceMorphMap()` is enforced in `AppServiceProvider::boot()`, any model exposed as a Nova resource that is missing from the map throws `ClassMorphViolationException` at runtime the moment a Nova action runs against it. When you add a Nova resource, add its model to `configureMorphMap()` in the same change — not just the models used in the app's own polymorphic relations.

(Kept in sync with the copy in the other file: .ai/rules/models.md and .ai/rules/providers.md carry the same text. Edit both together.)

## Models: PHP attributes, explicit Fillable, no auth() in model code
Models configure themselves with Laravel 13 attributes, not properties: #[Fillable], #[Hidden], #[Appends], #[ObservedBy], and #[Scope] on protected scope methods (no scopeXxx prefix). Never use $guarded = []: the legacy app did and left is_active, views and two_factor_* mass assignable. AppServiceProvider calls Model::preventSilentlyDiscardingAttributes(! isProduction()), so a missing Fillable entry throws in dev/test instead of silently dropping. Include FK and polymorphic *_type/*_id keys in Fillable — factories fill them.

Model code never calls auth(). Every helper that acts for a user takes an explicit User parameter (Pet::toggleLike(User), HasSaves::toggleSave(User), scopes withLikedBy/withSavedBy/withReportedBy(?User)); Actions pass the authenticated user in.

## Pet::nearby filters, withDistance selects and sorts — keep them apart
nearby($lat, $lng, $radiusKm) only adds WHERE clauses (bounding box with antimeridian wrap + Haversine in whereRaw, all values bound, never sprintf-interpolated). It deliberately adds no SELECT and no ORDER BY, because Builder::aggregate() strips columns but keeps orders, so a `distance` alias would break count().

Call withDistance($lat, $lng) when you need the value or the ordering; it selects the table's columns first (a bare selectRaw would otherwise drop every attribute) and orders by the alias. paginate() strips columns, orders and their bindings before counting, so pagination works with either.

The SQL relies on radians/cos/sin/acos, which SQLite has (math functions are compiled in), and clamps the cosine to [-1, 1] before acos so rounding cannot produce NULL/NaN.

## HasLikes gives you likes(); implementing Likeable is what enables notifications
`App\Observers\LikeObserver` early-returns on anything that is not an `App\Contracts\Likeable`, so a model that uses the `HasLikes` trait but does not implement the interface is likeable and silent. That is a valid choice, but it must be a deliberate one — `Comment` had the trait without the interface and every comment like went unnotified.

Adding `HasLikes` to a model: decide explicitly whether it also `implements Likeable` and returns recipients from `likeNotificationRecipients()`. Pet, User and Comment all do (owner / profile owner / comment author); the observer already drops self-likes.

## Privileged columns stay out of #[Fillable] (factories are unguarded anyway)
Columns the application owns rather than the user must not be mass assignable, because a controller forwarding `$request->validated()` into `create()`/`update()` would accept them. Currently excluded: `reports.status` (moderator decision, DB default `pending`), `messages.status`, `messages.pinned_by`, `messages.pinned_at` (delivery state and pinning are privileged). Have the moderation / pin Action set them explicitly.

Do not rely on the DB default alone for a non-fillable column the `@property` block declares non-nullable: the default lands in the row, not on the instance, so `Message::create([...])->status` and `Report::create([...])->status` read null until a refresh() and `->status->value` fatals in the API Resource or Inertia prop built from the returned model. Mirror the default in `protected $attributes` using the enum's **backing value** — `['status' => MessageStatus::Sent->value]`, `['status' => ReportStatus::Pending->value]` — and let the cast resolve it on access.

This costs factories nothing: `Illuminate\Database\Eloquent\Factories\Factory` wraps model construction in `Model::unguarded()`, so factory states can keep setting these columns. Removing a column from `#[Fillable]` never breaks a factory — only real request-driven mass assignment.

## Pet medical JSON shapes: vaccinations {name,date}, medications {name,usage}, additional_info a map
Verified against what the legacy form actually submits (petconnect-old Create.vue + StorePetRequest + PetService):
- vaccinations: list<array{name: string, date: string|null}> — the date is real user-entered clinical data with its own `date` validation rule. Do not flatten to a list of strings.
- medications: list<array{name: string, usage: string|null}> — {name, usage}, NOT {name, date}.
- allergies: array<int, string> — a plain list of strings.
- additional_info: array<string, mixed> — a fixed key map, deliberately NOT legacy's [{key, value}] repeater. The legacy detail page case-insensitively string-matched user-typed keys against hardcoded English labels, which breaks the moment a user types Arabic or a typo.
The casts stay 'array'; the shape is enforced by the Form Request and documented in the @property block. Phase 2/4 forms build against these shapes.
