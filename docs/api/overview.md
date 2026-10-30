# API v1 overview

A JSON API under `/api/v1`, built for the SniffPal Flutter app. It mirrors the web routes one-to-one and reuses the same Actions, Policies and Form Requests, so behaviour cannot drift between clients.

## Conventions

| Topic | Rule |
| --- | --- |
| Base path | `/api/v1`, route names `api.v1.*` |
| Auth | `Authorization: Bearer <token>` (Sanctum personal access token) |
| Locale | `X-Locale: ar` or `en`; defaults to the account locale, then `APP_LOCALE` |
| Content | `Accept: application/json`; responses are Eloquent API Resources |
| Errors | JSON for every path under `api/*`: 401 unauthenticated, 403 policy denial, 404 missing or invisible, 422 validation with `errors` keyed by field, 429 throttled |
| IDs | Listings are addressed by `slug`. Users, comments, reviews, conversations, messages by numeric id. Notifications by UUID |
| Public reads | Categories, options, listings, profiles, comments and reviews can be read without a token. The token, when present, still marks likes and ownership |
| Throttles | Same named limiters as the web routes ([Request pipeline](/architecture/middleware#rate-limiting)) |

## Authentication flow

```
POST /api/v1/auth/register            → { token, user }
POST /api/v1/auth/login               → { token, user }
POST /api/v1/auth/social/{provider}   → { token, user }   (google | facebook; provider access token in body)
POST /api/v1/auth/logout              → revokes the current token
POST /api/v1/auth/forgot-password
POST /api/v1/auth/email/verification-notification
```

Writes that need a verified email return 403 until the account verifies. Deactivating an account revokes every token; `EnsureAccountIsActive` on the API group enforces it.

## Account

```
GET    /api/v1/me             current account (AuthenticatedUserResource)
PATCH  /api/v1/me             profile fields, avatar
PUT    /api/v1/me/password
DELETE /api/v1/me             full account deletion through the same Action as the web
GET    /api/v1/me/badges      unread counts for notifications and messages
```

## Options endpoint

`GET /api/v1/options` (`BuildClientOptions`) returns, for the requested locale:

- enum options with values and labels (listing types, statuses, genders, health statuses, report categories and reasons, ticket types)
- validation limits the listing wizard needs (counts, lengths, max photos, image size)
- supported locales and cities

The mobile app fetches it at launch and builds its forms from it, so a server-side rule change reaches the app without a release.

## Mobile app

The Flutter client lives outside this repository. It was realigned in October 2026 to slug-based listing routes and to the 8-step wizard with the same client-side validation as the web. Social login and listing sharing on mobile are still open items.

See [Endpoints](/api/endpoints) for the full route table.
