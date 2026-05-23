---
name: laravel-security
description: "Use for PetConnect authentication, authorization, policies, verified-user behavior, Form Request authorization, user-submitted content, uploads, report/comment/review safety, messaging access, and sensitive data exposure review."
license: MIT
metadata:
  author: petconnect
---

# Laravel Security For PetConnect

## Auth Model

PetConnect uses web/session authentication with Laravel Fortify and custom auth controllers/routes.

- Auth routes are in `routes/auth.php`.
- Fortify currently provides two-factor authentication and password confirmation hooks.
- `User` implements `MustVerifyEmail` and `TwoFactorAuthenticatable`.
- Do not use Sanctum or token auth unless the user explicitly adds an API.

## Authorization

- Use middleware for route-level auth/verified checks.
- Use policies for model access.
- Use Form Request `authorize()` for request-specific checks.
- User-owned models typically require a verified owner through `App\Policies\Policy`.
- Conversations and messages must require participant access; message update/delete must require sender ownership.
- Comments, reviews, and reports must not trust client-supplied ownership or model class names.

## Input And Uploads

- Use Form Requests and `$request->validated()` for new domain endpoints.
- Do not use `$request->all()` for persistence.
- Validate uploaded image MIME/type/size; keep backend validation as source of truth even when frontend compresses images.
- Process comment content through `CommentService` to apply trimming and bad-word filtering.
- Keep report category/reason values constrained to enums.

## Sensitive Data

Never expose or log:

- `password`
- `remember_token`
- `two_factor_secret`
- `two_factor_recovery_codes`
- reset tokens
- raw request payloads containing credentials or private messages

Use `UserResource` and model `$hidden` fields to keep sensitive values out of Inertia props.

## Verification

- Test guest redirects, unverified restrictions, wrong-owner access, non-participant messaging access, and validation failures.
