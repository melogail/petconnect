# Public profiles

## Purpose
A user's public page: avatar, bio, location, verified badge, their listings, reviews, like count and contact buttons.

## Data
`users` (username as route key, bio, city/state/country, lat/lng, phone, show_phone, whatsapp_enabled, is_active), media collection `users` (avatar, conversions thumb/display).

## Backend
- `app/Actions/Profiles/LoadProfileForDisplay.php` loads listings, review summary and like state.
- Resources in `app/Http/Resources/Profile/` (public profile, form, authenticated user). `UserPolicy` governs who may view inactive profiles and edit.
- `User::whatsappUrl()` only returns a link when `whatsapp_enabled`; `isVerified()` reflects email verification.
- Controllers `Web/ProfileController@show`, `Api/V1/ProfileController` (show, pets, reviews).

## Routes
`GET profile/{user}` (profile.show), `POST profile/{user}/like`. API `GET profiles/{user}`, `/pets`, `/reviews`, `POST profiles/{user}/like`.

## Frontend
`pages/profile/Show.vue`. Phone and WhatsApp buttons render only for signed-in viewers and only when the owner enabled them (privacy settings).

## Guards
Throttle `profile-likes`. Inactive users return 404 to the public.

## Admin
Nova `User` resource; actions DeactivateUser, ReactivateUser, DeleteUserAccount; metrics TotalUsers, NewUsers, AverageUsers.

## Tests
`tests/Feature/Http/Controllers/Web/`, `tests/Feature/Http/Resources/Profile/`, `tests/Feature/Policies/`.

## Open work
- No verified-seller or breeder badge beyond email verification; this was identified as the main trust differentiator.
- Profile pages have no Open Graph title/description.
