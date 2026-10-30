# Request pipeline

`bootstrap/app.php` configures the middleware groups. The comments in that file are the long form; this page is the map.

## Web group

Measured run order on a guarded route such as `pets.store` (`['auth', 'verified']`):

```
EncryptCookies → AddQueuedCookiesToResponse → StartSession → ShareErrorsFromSession
→ PreventRequestForgery → Authenticate → ThrottleRequests → SubstituteBindings
→ EnsureAccountIsActive → SetLocale → HandleAppearance → HandleInertiaRequests
→ InjectBoost (local only) → EnsureEmailIsVerified
```

::: warning Ordering is not what the file suggests
`Kernel::$middlewarePriority` re-sorts the gathered stack. Anything in the priority list (`Authenticate`, `ThrottleRequests`, `SubstituteBindings`, `EnsureEmailIsVerified`, …) is pulled into its canonical position; everything else stays where it was appended. Appended group middleware therefore do **not** necessarily run before route middleware. If your middleware must wrap an authentication redirect, add it to the priority list, as `DisallowRobots` is for the docs host.
:::

Project middleware on the web group:

| Middleware | Does |
| --- | --- |
| `EnsureAccountIsActive` | Signs out a deactivated member before any controller or Inertia prop runs. |
| `SetLocale` | Resolves `ar`/`en` from the user preference, then the plaintext `locale` cookie, then the app default. Runs after deactivation so the right user's preference applies, and before Inertia so every `__()` is already translated. |
| `HandleAppearance` | Reads the plaintext `appearance` cookie (light/dark/system). |
| `HandleInertiaRequests` | Builds shared props: app name, `auth.user`, `locale {current, direction, supported}`, the translation catalogue (sent once per session), flash messages. |

Two cookies are excluded from encryption, `appearance` and `locale`, so the client can set `lang` and `dir` on the document before the first Inertia payload arrives.

`AddLinkHeadersForPreloadedAssets` is deliberately **not** appended. Its `Link:` header exceeds nginx's default 4 KB `fastcgi_buffer_size` and produced 502s through Valet.

## API group

```
UseSanctumGuard (prepended) → … → EnsureAccountIsActive → SetLocale
```

- `UseSanctumGuard` makes the bearer token's owner `$request->user()` for every later middleware, policy and resource, including public reads with no `auth` middleware.
- `EnsureAccountIsActive` also revokes the token of a deactivated account.
- `SetLocale` reads the `X-Locale` header, since there is no cookie.
- `HandleAppearance` and `HandleInertiaRequests` are not applied.
- Exceptions render as JSON when the path is `api/*` or the request expects JSON.

## Rate limiting

There is no uncapped mutating route in the application. Named limiters are defined in `RateLimitServiceProvider::configureRateLimiters()`:

| Limiter | Used for |
| --- | --- |
| `content-edits` | Default home: single-row update/destroy/toggle on a row the caller owns (30/min) |
| `inbox-actions` | Client-fired inbox housekeeping: read-all, clear-all (60/min) |
| `pet-likes`, `comment-likes`, `profile-likes` | Like toggles |
| `comments`, `messages`, `reviews`, `reports`, `conversations`, `ticket-messages`, `contact-messages` | Content creation |
| `pet-listings`, `pet-listing-edits`, `profile-updates` | Anything that stores an image or runs a conversion |
| `locale-switches` | Public, IP-keyed |
| `registrations`, `password-reset-links`, `password-resets`, `password-changes`, `password-confirmations`, `verification-notifications`, `social-logins` | Auth flows |
| `account-deletions` | `DELETE settings/profile` |

Rules when adding a route:

- Owned single-row write: `throttle:content-edits`. Nothing to size.
- Image-storing write: one of the listing/profile limiters, because the upload is decoded and stored in the request (conversions themselves are queued).
- Public unauthenticated write: an IP-keyed limiter, never `rateLimitKey()`.
- Never write "deliberately not throttled".

Read-only routes (including the docs host) carry no limiter.
