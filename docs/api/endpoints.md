# API v1 endpoints

Generated from `php artisan route:list --path=api`. Run it for the live list; this table was taken in October 2026. "Auth" marks routes that require a bearer token; "Verified" additionally requires a verified email.

## Auth

| Method | Path | Auth | Limiter |
| --- | --- | --- | --- |
| POST | `/auth/register` | — | `registrations` |
| POST | `/auth/login` | — | login throttle |
| POST | `/auth/social/{provider}` | — | `social-logins` |
| POST | `/auth/logout` | Auth | — |
| POST | `/auth/forgot-password` | — | `password-reset-links` |
| POST | `/auth/email/verification-notification` | Auth | `verification-notifications` |

## Account

| Method | Path | Auth | Limiter |
| --- | --- | --- | --- |
| GET | `/me` | Auth | — |
| PATCH | `/me` | Auth | `profile-updates` |
| DELETE | `/me` | Auth | `account-deletions` |
| PUT | `/me/password` | Auth | `password-changes` |
| GET | `/me/badges` | Auth | — |

## Reference data

| Method | Path | Auth |
| --- | --- | --- |
| GET | `/categories` | — (with breeds) |
| GET | `/options` | — |

## Listings

| Method | Path | Auth | Limiter |
| --- | --- | --- | --- |
| GET | `/pets` | — | — |
| POST | `/pets` | Verified | `pet-listings` |
| GET | `/pets/{pet}` | — | — |
| PUT | `/pets/{pet}` | Verified, owner | `pet-listing-edits` |
| DELETE | `/pets/{pet}` | Verified, owner | `content-edits` |
| POST | `/pets/{pet}/like` | Verified | `pet-likes` |
| PATCH | `/pets/{pet}/status` | Verified, owner | `content-edits` |

`{pet}` is the slug. `GET /pets` accepts the same filters as the web feed: category, breed, listing type, gender, status, city, and `lat`/`lng`/`radius` for nearby search, plus pagination.

## Profiles

| Method | Path | Auth | Limiter |
| --- | --- | --- | --- |
| GET | `/profiles/{user}` | — | — |
| GET | `/profiles/{user}/pets` | — | — |
| GET | `/profiles/{user}/reviews` | — | — |
| POST | `/profiles/{user}/like` | Verified | `profile-likes` |

## Comments

| Method | Path | Auth | Limiter |
| --- | --- | --- | --- |
| GET | `/comments/{commentable_type}/{commentable_id}` | — | — |
| POST | `/comments/{commentable_type}/{commentable_id}` | Verified | `comments` |
| GET | `/comments/{comment}/replies` | — | — |
| PUT | `/comments/{comment}` | Verified, author | `content-edits` |
| DELETE | `/comments/{comment}` | Verified, author | `content-edits` |
| POST | `/comments/{comment}/like` | Verified | `comment-likes` |

`{commentable_type}` is the morph alias (`pet`); `{commentable_id}` is the listing slug.

## Reviews

| Method | Path | Auth | Limiter |
| --- | --- | --- | --- |
| GET | `/reviews/{reviewable_type}/{reviewable_id}` | — | — |
| POST | `/reviews/{reviewable_type}/{reviewable_id}` | Verified | `reviews` |
| PUT | `/reviews/{review}` | Verified, author | `content-edits` |
| DELETE | `/reviews/{review}` | Verified, author | `content-edits` |

`{reviewable_type}` is `user`.

## Reports

| Method | Path | Auth | Limiter |
| --- | --- | --- | --- |
| POST | `/reports/{reportable_type}/{reportable_id}` | Verified | `reports` |

`{reportable_type}` is `user`, `comment` or `review`.

## Messaging

| Method | Path | Auth | Limiter |
| --- | --- | --- | --- |
| GET | `/conversations` | Auth | — |
| POST | `/conversations` | Verified | `conversations` |
| GET | `/conversations/{conversation}` | Auth, participant | — |
| GET | `/conversations/{conversation}/messages` | Auth, participant | — |
| POST | `/conversations/{conversation}/messages` | Verified, participant | `messages` |
| POST | `/conversations/{conversation}/read` | Auth, participant | `inbox-actions` |
| PUT | `/messages/{message}` | Auth, sender | `content-edits` |
| DELETE | `/messages/{message}` | Auth, sender | `content-edits` |
| POST | `/messages/{message}/pin` | Auth, participant | `content-edits` |

## Notifications

| Method | Path | Auth | Limiter |
| --- | --- | --- | --- |
| GET | `/notifications` | Auth | — |
| POST | `/notifications/{notification}/read` | Auth | `content-edits` |
| POST | `/notifications/read-all` | Auth | `inbox-actions` |
| DELETE | `/notifications` | Auth | `inbox-actions` |

## Response shapes

Resources under `app/Http/Resources` define the payloads; the `resources/js/types` TypeScript models and the Flutter domain models mirror them. Relations rendered through `whenLoaded()` are present only when the controller eager-loaded them; a missing key on a documented field is a server bug, not an optional field.
