# Architecture overview

SniffPal is a single Laravel application that serves three clients: the Inertia/Vue web app, the Nova admin panel, and a JSON API consumed by the Flutter mobile app.

```
Browser ──Inertia──▶ routes/web.php ─┐
Admin   ──Nova─────▶ /nova           ├─▶ Controllers ─▶ Actions ─▶ Pipelines ─▶ Models
Mobile  ──Sanctum──▶ routes/api.php ─┘        │
                                              └─▶ Policies · Observers · Notifications · Broadcasting
```

## Stack

| Layer | Package | Version |
| --- | --- | --- |
| Framework | laravel/framework | 13.31 on PHP 8.4 |
| Web UI | inertiajs/inertia-laravel + @inertiajs/vue3 | 3.x |
| View layer | Vue 3.5, Tailwind CSS 4, reka-ui, shadcn-style components | |
| Build | Vite 8 through `vite-plus` (`vp`), Wayfinder route generation | |
| Auth | laravel/fortify 1.39, laravel/passkeys, laravel/socialite 5.31, laravel/sanctum 4.3 | |
| Admin | laravel/nova 5.10 + ebess/advanced-nova-media-library 5.2 | |
| Media | spatie/laravel-medialibrary 11.23 | |
| Real time | laravel/reverb 1.11, laravel-echo + @laravel/echo-vue | |
| Queues | laravel/horizon 5.49 (database queue by default locally) | |
| Quality | pestphp/pest 5.1, larastan 3.10, laravel/pint, laravel/boost | |

Confirm a version with `composer show <vendor/package>` or `package.json` before relying on an API; do not assume from this table.

## Source layout

```
app/
  Actions/        one class per use case, grouped by domain
  Pipelines/      multi-step flows run by an Action; Shared/ steps are reusable
  Http/
    Controllers/  Web/, Settings/, Api/V1/ — thin, authorize + delegate
    Middleware/   request-scoped behaviour (locale, account state, guards)
    Requests/     validation only, never authorization
    Resources/    JSON shapes for Inertia props and the API
  Models/         Eloquent models and relations
  Policies/       member-facing authorization
  Nova/           admin resources, actions, metrics, dashboards, Policies/
  Observers/      model lifecycle hooks (e.g. slug assignment on Pet)
  Concerns/       traits shared by models (likes, saves, validation rules)
  Enums/          backed enums for every fixed vocabulary
  Notifications/  queued database + broadcast notifications
  Providers/      AppServiceProvider, RateLimitServiceProvider, Fortify, Nova, Horizon
  MediaLibrary/   medialibrary customisations (paths, decode checks, temp cleanup)
resources/js/     Vue pages, components, layouts, composables, generated routes
routes/           web.php, settings.php, docs.php, api.php, channels.php, console.php
database/         migrations, factories, seeders, data/*.json (breeds, categories, locations)
lang/             en.json, ar.json plus framework php files per locale
tests/            Pest feature and unit tests
.ai/rules/        committed decisions and traps, path-scoped
docs/             this VitePress site
```

## Principles in force

- **Thin controllers.** A controller authorises, validates (through a Form Request) and hands off to an Action. Business logic lives in Actions and Pipelines; there is no service or repository layer.
- **Eloquent, guarded.** `Model::preventLazyLoading()` is on. Query logic belongs in Actions and model scopes.
- **Enums everywhere.** Listing type, status, health status, gender, report category/reason/status, message type/status, ticket type and social provider are all backed enums with translated labels.
- **Everything throttled.** Every mutating route names a rate limiter.
- **Two guards.** Members on `web`/Sanctum, staff on `admin`. They never share a table or a session.
- **Bilingual by construction.** Every UI string exists in English and Arabic and a test enforces parity.

See the following pages for each layer in detail, and the [Codebase](/codebase/listing-lifecycle) section for the same layers shown as real code.
