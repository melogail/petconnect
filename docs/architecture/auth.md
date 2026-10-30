# Authentication & admin

Two populations, two tables, two guards. They never overlap.

| | Members | Staff |
| --- | --- | --- |
| Table / model | `users` → `App\Models\User` | `admins` → `App\Models\Admin` |
| Guard | `web` (session), `sanctum` (API token) | `admin` (session) |
| Login UI | `/login` (Inertia, Fortify) | `/nova/login` |
| Password broker | `users` | `admins` |
| Surfaces | Web app, API v1 | Nova, this docs site |

`config/auth.php` defines both guards and providers; `.env` sets `NOVA_GUARD=admin` and `NOVA_PASSWORDS=admins`.

## Members: Fortify

`FortifyServiceProvider` wires the Actions under `app/Actions/Fortify` and the Inertia pages under `resources/js/pages/auth`. Features in use:

- Registration, login, logout, password reset, password confirmation
- Email verification (the `verified` middleware guards listing creation and other writes)
- Two-factor authentication with TOTP, QR code and recovery codes
- Passkeys (WebAuthn) via `laravel/passkeys`; `/.well-known/passkey-endpoints` is published
- Settings pages: `settings/profile`, `settings/security`, `settings/password`

Every auth write is throttled (`registrations`, `password-resets`, `password-changes`, …).

## Members: social login

Google and Facebook through Socialite (`SocialProvider` enum, `social_accounts` table, `SocialAuthController` on the web and `SocialLoginController` on the API).

Both entry points share `Actions\Auth\ResolveSocialUser`. Three settled decisions, recorded in `.ai/rules/actions.md`:

1. `social.callback` is the one GET that writes (OAuth fixes the method). It is safe only because the login buttons are plain `<a>` tags that Inertia never prefetches. Do not cite it as precedent.
2. A social-registered account gets a random 32-character password, not a nullable one, because every settings flow asks for `current_password`. Such a member sets a real password through "Forgot password?".
3. Accounts are matched and linked by the provider's email, which Google and Facebook only return when verified. A provider that returns no email is refused. New accounts are marked verified immediately.

The redirect registered with the provider console must be exactly `APP_URL + /auth/{provider}/callback`. No provider tokens are stored.

## Members: API tokens

Mobile clients call `POST /api/v1/auth/login` (or `/register`, or `/auth/social/{provider}`) and receive a Sanctum personal access token. `UseSanctumGuard` makes the token's owner the request user on every API route. `DELETE /api/v1/me` and account deactivation revoke tokens.

## Deactivation

`users.is_active` is the single switch. A deactivated member is logged out on the next request, cannot log in, has API tokens revoked, disappears from public profiles and listings, and is not addressable by id. Staff toggle it in Nova with `DeactivateUser` / `ReactivateUser`.

## Staff: Nova

- Nova lives at `/nova` on any host (no `NOVA_DOMAIN`), authenticates on the `admin` guard and has its own password reset. Email verification routes are disabled.
- `NovaServiceProvider::gate()` is explicit: the callback type-hints `Admin`, and the provider documents why the guard and the gate are both kept.
- Resources: Admin, Breed, Category, Pet, User, Comment, Review, Report, Ticket, TicketMessage, ContactMessage and more under `app/Nova`, with dashboard metrics (`NewUsers`, …).
- **Policies** under `app/Nova/Policies` return `false` for irreversible built-in deletes. The replacement is a destructive action (`DeleteUserAccount`, `PurgePetListing`, `DeleteCommentThread`, `DeleteReview`, `DeleteCategory`) that wraps the selection in a transaction, walks the polymorphic children, and reports failures. `report()` inside such an action cannot be observed by `Exceptions::fake()` in tests because Nova swaps the exception handler.
- Nova actions are the moderation surface for reports: review, resolve, purge content, deactivate the author.

## Staff: this documentation

`routes/docs.php` applies Nova's `Authenticate` middleware to the docs host. An admin session is required; a member session is treated as a guest and redirected to `/nova/login`. See [This docs site](/guide/docs-site).

## Member-facing authorization

`app/Policies` holds one policy per member-editable model (Pet, Comment, Message, Review, Conversation, …). Controllers call `$this->authorize()`; Form Requests never do. Ownership bounds *what* a caller can touch; throttles bound *how often*, and both are required.
