# Authentication

## Purpose
Session auth for the web app and token auth for the API, both driven by Laravel Fortify. Covers registration, login, logout, password reset, email verification, two-factor (TOTP), passkeys (WebAuthn) and password confirmation.

## Data
`users` (password, email_verified_at, two_factor_secret, two_factor_recovery_codes, two_factor_confirmed_at, is_active), `passkeys`, `password_reset_tokens`, `personal_access_tokens`. See [data-model.md](data-model.md).

## Backend
- `config/fortify.php` enables registration, resetPasswords, emailVerification, twoFactorAuthentication, passkeys.
- `app/Actions/Fortify/CreateNewUser.php`, `ResetUserPassword.php`; `app/Actions/Users/RegisterUser.php` is the shared registration action used by web and API.
- `app/Actions/Auth/`: AuthenticateWithPassword, VerifyTwoFactorCode, IssueAccessToken, RevokeAccessTokens, ResolveSocialUser.
- `app/Providers/FortifyServiceProvider.php` wires views to the Inertia pages and binds the actions.
- Notifications: `VerifyEmailNotification` (mail).
- Middleware: `EnsureAccountIsActive` (blocks `is_active = false`), `ForgetForeignIntendedUrl`, `ThrottleAuthRoutes`, `UseSanctumGuard`.
- Form requests: `app/Http/Requests/Auth/{LoginRequest,RegisterRequest,PasswordResetLinkRequest,SocialLoginRequest}`, `Settings/{PasswordUpdateRequest,TwoFactorAuthenticationRequest}`; shared rules in `App\Concerns\PasswordValidationRules`.

## Routes
- Web: Fortify's standard routes (`/login`, `/register`, `/forgot-password`, `/reset-password`, `/email/verify`, `/two-factor-challenge`, `/user/confirm-password`, 2FA and passkey management) plus `GET .well-known/passkey-endpoints`.
- API: `POST api/v1/auth/{register,login,logout,forgot-password,email/verification-notification}` (see [api-v1.md](api-v1.md)).

## Frontend
`resources/js/pages/auth/`: Login, Register, ForgotPassword, ResetPassword, VerifyEmail, TwoFactorChallenge, ConfirmPassword. 2FA and passkey management live in `settings/Security.vue` (see [settings.md](settings.md)). Uses `@laravel/passkeys` and `vue-input-otp`.

## Guards
Throttles: registrations, login, password-reset-links, password-resets, password-confirmations, verification-notifications, password-changes (see [rate-limiting.md](rate-limiting.md)). Posting a pet, messaging and most writes require `verified`.

## Admin
Nova `DeactivateUser` / `ReactivateUser` actions flip `is_active`; `DeleteUserAccount` runs the full purge pipeline.

## Tests
`tests/Feature/Auth/`, `tests/Feature/Http/Middleware/`, `tests/Feature/Settings/`.

## Open work
- No phone verification.
- Email verification is required before posting; consider whether that gate hurts first-post conversion (business note, not a bug).
