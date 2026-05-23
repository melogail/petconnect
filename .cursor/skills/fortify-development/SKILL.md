---
name: fortify-development
description: "Use for PetConnect authentication and account security work: login, registration, password reset, email verification, password confirmation, two-factor authentication, Fortify config/provider, auth routes/controllers, settings security pages, and user auth state."
license: MIT
metadata:
  author: petconnect
---

# Fortify And Auth For PetConnect

## Current Shape

- `config/fortify.php` currently enables Fortify two-factor authentication with confirmation and password confirmation.
- Registration, login, password reset, and email verification routes/controllers live in `routes/auth.php` and `App\Http\Controllers\Auth`.
- `FortifyServiceProvider` defines two-factor challenge and confirm-password Inertia views.
- `User` implements `MustVerifyEmail` and uses `TwoFactorAuthenticatable`.
- Settings 2FA UI lives in `resources/js/pages/settings/TwoFactor.vue` and related components/composables.

Do not assume every Fortify feature in `config/fortify.php` is enabled just because the custom app has a matching auth page.

## Frontend

- Auth pages live in `resources/js/pages/auth`.
- Settings pages live in `resources/js/pages/settings`.
- Auth forms commonly use Inertia `<Form>` and generated Wayfinder controller action imports.
- Reuse `InputError`, `TextLink`, `AuthLayout`, `Button`, `Input`, `Label`, 2FA setup/recovery components, and `useTwoFactorAuth`.

## Security

- Preserve session regeneration on login/registration.
- Preserve signed/throttled email verification routes.
- Do not expose `two_factor_secret`, `two_factor_recovery_codes`, passwords, or remember tokens.
- Respect verified-user requirements for creating/updating/deleting user-owned records.

## Verification

- Run focused auth/settings tests for auth changes.
- Test guest, authenticated, unverified, verified, and password-confirmed paths where relevant.
