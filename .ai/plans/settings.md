# Settings

## Purpose
Signed-in account management: profile details and avatar, password, 2FA, passkeys, privacy toggles, appearance, support tickets, and account deletion.

## Data
`users` fields (see [data-model.md](data-model.md)), media collection `users`, Fortify 2FA columns, `passkeys`. Appearance is a client-side preference persisted by `HandleAppearance` middleware (cookie).

## Backend
- Controllers (`app/Http/Controllers/Settings/`): ProfileController (edit, update, destroy), SecurityController (edit, update password), PrivacyController (edit), TicketController (see [support-tickets.md](support-tickets.md)).
- Actions (`app/Actions/Profiles/`): UpdateProfile, UpdatePassword, ApplyUserLocale, DeleteUserAccount.
- Pipelines: UpdateProfile (EnsureProfileImageIsDecodable → ClearPreviousProfileImage → UploadProfileImage → PersistProfileAttributes → ApplyLocalePreference); DeleteAccount (CollectAccountContent → DeleteContentComments → DeleteContentLikes → DeleteContentSaves → DeleteContentReports → DeleteReviewsAboutAccount → DeleteAccountNotifications → PurgeOwnedListings → DeleteAccountRecord).
- Requests: `Profile/{UpdateProfileRequest,DeleteProfileRequest,UpdateLocaleRequest}`, `Settings/{PasswordUpdateRequest,TwoFactorAuthenticationRequest}`; rules in `ProfileValidationRules`, `PasswordValidationRules`.

## Routes (`routes/settings.php`, auth + verified)
`GET settings` → redirect to profile; `GET|PATCH|DELETE settings/profile`; `GET settings/privacy`; `GET settings/security`; `PUT settings/password`; `GET settings/appearance` (Inertia shortcut); tickets routes. API: `GET|PATCH|DELETE api/v1/me`, `PUT api/v1/me/password`.

## Frontend
`pages/settings/{Profile,Security,Privacy,Appearance,Tickets,Ticket}.vue` sharing a settings layout. Profile shows the avatar at the top; privacy holds `show_phone` and `whatsapp_enabled`.

## Guards
Throttles `profile-updates` (10/min), `password-changes` (6/min), `account-deletions` (10/min, 20/h), `password-confirmations`. Deletion requires password confirmation.

## Tests
`tests/Feature/Settings/`, `tests/Feature/Actions/Profiles/`.

## Open work
- Privacy settings update currently goes through the profile update endpoint; a dedicated request would let the privacy page validate only its own fields.
- No data export for the account.
