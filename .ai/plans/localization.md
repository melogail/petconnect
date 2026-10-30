# Localization

## Purpose
English and Arabic UI with RTL, per-user locale preference, and a translation catalogue shipped to the client once.

## Data
`users.locale`, `users.timezone`. Translations in `lang/en` and `lang/ar` (rules: `.ai/rules/lang.md`). Supported and RTL locales in `config/petconnect.php` (`locales.supported`, `locales.rtl`).

## Backend
- `SetLocale` middleware picks the locale from the user, session or `Accept-Language`.
- `app/Actions/Localization/BuildTranslationCatalogue.php` flattens the lang files; `HandleInertiaRequests` shares it as `translations` via `Inertia::once()` and shares `locale` (current, direction, supported).
- `Web/LocaleController@update` + `UpdateLocaleRequest` switch the session/user locale; `ApplyUserLocale` persists it for signed-in users. `User::preferredLocale()` localises notifications and mail.
- Bilingual model columns: `name_ar`, `description_ar` on categories and breeds.

## Routes
`POST locale` (locale.update), throttle `locale-switches` (60/min).

## Frontend
`t()` helper over the shared catalogue; `dir` attribute from `locale.direction`; locale switcher in the shell.

## Tests
`tests/Feature/Actions/Localization/`, `tests/Feature/Http/Middleware/`.

## Open work
- `APP_LOCALE` is still `en`; the business plan calls for Arabic as default for the Egyptian launch.
- Enum labels and notification text must be checked for Arabic completeness whenever a case is added.
