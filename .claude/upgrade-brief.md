# PetConnect → Laravel 13 upgrade — maystro brief

Hand this to `maystro`. Written after a read-only survey of both codebases on 2026-08-30.

## The two codebases

**SOURCE (old, read-only reference — never modify):** `/home/asciisd/sites/petconnect-old`

- Laravel 12.51, PHP 8.2, Inertia 2, Pest 4, Ziggy 2.6, Nova 5, spatie/laravel-medialibrary 11, ebess/advanced-nova-media-library 5
- 133 PHP files in `app/`, 13 models, 26 migrations, 12 seeders, 32 test files
- 192 Vue components, ~19,465 lines of Vue + ~1,248 lines of TS, 17 pages
- Also contains `REFACTORING_SUMMARY.md` and `PET_FORM_IMPROVEMENTS.md` — read them, they record prior intent.

**TARGET (this project):** `/home/asciisd/sites/petconnect` — clean Laravel 13.17 / PHP 8.4 Vue starter kit. Inertia v3, Pest 5, Fortify, Wayfinder 0.1.14, Larastan, Pint. Only auth/settings scaffolding exists today. All work happens here.

## Inventory of the source

**Models (13):** Admin, Breed, Category, Comment, Conversation, Like, Message, Pet, Profile, Report, Review, Save, User

**Enums (6):** Commentable, ListingType, PetStatus, ReportCategory, ReportReason, ReportStatus

**Controllers (20):** `CommentController`; `Auth/` — AuthenticatedSession, RegisteredUser, EmailVerificationNotification, EmailVerificationPrompt, VerifyEmail, NewPassword, PasswordResetLink; `Web/` — Home, Pet, Profile, Comment-adjacent, Conversation, Message, Notification, Review, Report, Locale; `Api/` — Home, Comment

**Legacy shape to refactor away:** `app/Services/` (CommentService, ConversationService, MessageService, MessagingInboxService, NotificationInboxService, PetService, ProfileImageService), `app/Repositories/` + a second `app/Http/Repository/`, only 4 Actions (CreateReport, RegisterUserAction, CreateReviewAction, UpdateUserProfileAction) and 2 Pipelines (TrimContentPipeline, FilterBadWordsPipeline). This is legacy structure, **not** a template.

**Nova:** Admin, Breed, Category, Pet, User resources + `Resource.php` base, `Dashboards/`, `Metrics/`, `Policies/`

**Migrations (26):** users/cache/jobs, two-factor columns, admins + admin password resets, categories, breeds, pets, comments, likes, saves, media, user bio, user media directory name, pet views, reviews, reports, conversations, conversation_user (+ unique index), messages, notifications, category translations, breed translations, pets lat/long index

**Seeders (12):** Admin, Breed, Category, Comment, Like, Message, Pet, Report, Review, Save, User, DatabaseSeeder

**Tests (32):** Feature — NovaAdminAuthorization, NovaSharedProps, NovaMenu, HomeComments, HomeFilters, NearbyPets, PetLike, PetDestroy, CategoryMedia, CategoryBreedSeeder, Messaging, CommentStore, CommentUpdateDelete, Locale, Notification, ReportReview, HasLikes, InertiaFlashProp, Example; Feature/Auth — Authentication, Registration, EmailVerification, VerificationNotification, VerifyEmailNotification, PasswordReset, PasswordConfirmation, TwoFactorChallenge; Feature/Settings — ProfileUpdate, PasswordUpdate, TwoFactorAuthentication; Feature/Services — PetService; Unit — Example

**Pages (17):** Home; `auth/` — Login, Register, ForgotPassword, ResetPassword, ConfirmPassword, VerifyEmail, TwoFactorChallenge; `pet/` — Create, Edit, Show; `profile/` — Edit, Show; `messaging/` — Index, Show; `Help/Index`; `Support/Index`

**Component dirs:** `components/ui`, `components/web`, `components/pet`, `components/messaging` + ~19 top-level (AppShell, AppHeader, AppSidebar, NavMain, NavUser, NavFooter, UserMenuContent, UserInfo, Breadcrumbs, Icon, AppLogo, AppLogoIcon, TextLink, InputError, AlertError, Heading, AppContent, AppSidebarHeader, PlaceholderPattern)

**Routes:** `web.php` 83 lines, `auth.php` 52 lines, `console.php` 8 lines

**Feature surface:** pets (listings/adoption, categories, breeds, media, geo/nearby search with a lat-long index, Google Maps + Leaflet), comments, likes, saves, reviews, reports/moderation, direct messaging (conversations/messages), notifications inbox, user profiles, localization (`lang/`, LocaleController), Fortify auth incl. 2FA, Nova admin panel.

## Known upgrade deltas the plan must account for

- **Laravel 12 → 13**, PHP 8.2 → 8.4.
- **Inertia 2 → 3**: axios removed (use the built-in XHR client), `Inertia::lazy()`/`LazyProp` gone → `Inertia::optional()`, `invalid` event → `httpException`, `exception` → `networkError`, `router.cancel()` → `router.cancelAll()`, SSR via `@inertiajs/vite`, the `future` config namespace is gone. Confirm each via `search-docs` rather than trusting this list.
- **Ziggy → Wayfinder.** The target has no Ziggy. Every `route()` call in ported Vue code becomes a Wayfinder import from `@/actions` or `@/routes`. This touches a lot of files — plan for it explicitly.
- **Pest 4 → 5.**
- **Nova 5 → current.** Composer credentials for `nova.laravel.com` are already in `~/.config/composer/auth.json`, so installation will authenticate. **Verify Nova's Laravel 13 compatibility before committing to a version** — if no Nova release supports Laravel 13, escalate rather than downgrading the framework.
- **medialibrary 11 → 12** and `ebess/advanced-nova-media-library` — check both against Laravel 13 / current Nova. If `ebess` has no compatible release, escalate with a proposed alternative rather than blocking the upgrade.
- **Frontend deps to re-establish:** tailwind 4, reka-ui, lucide-vue-next, embla-carousel-vue, leaflet + @types/leaflet, @googlemaps/js-api-loader, browser-image-compression, vue-sonner, sonner, @vueuse/core, @headlessui/vue, class-variance-authority, clsx, tailwind-merge, tw-animate-css, next-themes. **Drop `ziggy-js`.** Question `vue-router` — Inertia handles routing; find out what the old project used it for before porting it.

## Required outcome

1. Dependencies installed and the app boots on Laravel 13, Nova included, migrations clean.
2. Every feature ported and **refactored** to the target architecture — not copy-pasted.
3. Seeders and factories for all models, enough for a realistic dev dataset.
4. Tests covering ported behaviour and its failure modes.
5. Security audit — authorization gaps, mass assignment, unvalidated input, IDOR on messaging/comments/reports, file-upload handling, Nova policy coverage, exposed secrets. Fix what is found.
6. Performance audit — N+1s, missing indexes, unbounded queries, eager loading, media handling, frontend bundle weight. Fix what is found.

## Architecture the refactor must land on

This is the point of the exercise; the old code's shape is not to be preserved.

- Controllers validate via Form Request, delegate, respond. Nothing else.
- The 7 legacy `app/Services/` classes decompose into single-purpose Actions in `app/Actions/{Domain}/`. A service holding six unrelated methods is six Actions.
- Any multi-step flow becomes a Pipeline under `app/Pipelines/{Domain}/{Flow}/` with a typed context object. Scaffold with `php artisan make:pipeline` (exists in the target). Strong candidates: pet creation with media, report submission/moderation, message send, user registration, review creation.
- Decide deliberately whether the Repository layer survives. Anaemic Eloquent wrapper → drop it. Earns its place → keep it behind an interface. Record the decision with `record-rule`.
- Frontend: components aggressively extracted, pages thin, reuse `resources/js/components/ui/**` first, simple over clever.
- `.ai/rules/index.md` and its rule files encode these constraints — pass the relevant ones down in every brief.

## Phasing

Too large for one pass. Work in phases, reporting after each:

0. **Discovery & dependencies** — deep read of the old codebase, compatibility resolution, install everything, confirm boot.
1. **Data layer** — models, migrations, enums, factories, seeders.
2. **Backend domain** — Actions, Pipelines, controllers, form requests, policies, API resources, routes, notifications, observers.
3. **Nova** — resources, dashboards, metrics, policies.
4. **Frontend** — layouts, components, pages, Inertia v3 + Wayfinder migration.
5. **Tests** — port and extend.
6. **Security & performance audit** — fixes routed to the owning agent.

Enforce normal discipline throughout: one agent per task, strict territory, and the `code-reviewer` gate after **every** sub-agent task including within phases. Maystro writes no code.

Where a judgement call has no clear answer, make the reasonable choice, note the assumption, and keep moving. Escalate only for hard blockers — an incompatible dependency with no viable path, or a decision that would discard user data or meaningfully change product behaviour.
