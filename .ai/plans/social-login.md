# Social login

## Purpose
Sign in or register with Google or Facebook, on the web (redirect flow) and from the mobile app (token exchange).

## Data
`social_accounts` (user_id, provider, provider_id). Providers: `SocialProvider` enum (google, facebook). Credentials in `config/services.php` from `GOOGLE_*` / `FACEBOOK_*` env.

## Backend
- `app/Actions/Auth/ResolveSocialUser.php` finds the social account, else matches by email, else registers a new user through `RegisterUser`.
- Web: `app/Http/Controllers/Web/SocialAuthController.php` (redirect + callback).
- API: `app/Http/Controllers/Api/V1/Auth/SocialLoginController.php` takes a provider access token, validated by `SocialLoginRequest`, returns a Sanctum token.

## Routes
- `GET auth/{provider}/redirect` (social.redirect), `GET auth/{provider}/callback` (social.callback).
- `POST api/v1/auth/social/{provider}` (api.v1.auth.social).

## Frontend
Buttons on `pages/auth/Login.vue` and `Register.vue`.

## Guards
Throttle `social-logins` (10/min, 60/h per caller). Provider must be a valid `SocialProvider` case.

## Tests
`tests/Feature/Auth/` and `tests/Feature/Http/Controllers/Api/V1/` (Socialite fakes).

## Open work
- Set real OAuth client IDs and redirect URIs for the production domain before launch.
- Flutter app side of social login is still open (see memory note on the mobile app).
