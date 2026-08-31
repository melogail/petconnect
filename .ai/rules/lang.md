---
paths:
  - 'lang/**'
---

# Lang

## The locale layer: one writer, one reader, one whitelist
`lang/` holds `en/` + `ar/` (framework files; `en/` from `lang:publish`, `ar/` ported from petconnect-old and topped up so it has zero missing keys) and `en.json` + `ar.json` (dotted UI/mail/notification keys ported from the legacy app, plus every English-source string the backend passes to `__()`).

Two key styles coexist, deliberately: backend toasts and domain exceptions use the English sentence as the key (so `en.json` needs no entry for them, only `ar.json` does); notification payloads, mail and frontend copy use dotted keys (`notifications.*`, `mail.verify_email.*`, `profile.*`, `locales.*`). Do not "normalise" one into the other.

Mechanism, and there is exactly one of each role:
- **Whitelist** — `config('petconnect.locales.supported')`. Read by `SetLocale`, `ProfileValidationRules::localeRules()`, `ApplyUserLocale` and `User::preferredLocale()`. RTL languages are `petconnect.locales.rtl`; never hardcode `=== 'ar'`.
- **Writer** — `Actions\Profiles\ApplyUserLocale`. The only thing that calls `App::setLocale()` outside the middleware, the only thing that queues the `locale` cookie (plaintext, in the `encryptCookies` except-list) and the only thing that writes `users.locale` for a switch. Two callers: `Web\LocaleController` and `Pipelines\Profiles\UpdateProfile\ApplyLocalePreference`.
- **Reader** — `Http\Middleware\SetLocale`, in the `web` group. Precedence cookie → user → session → `app.locale`, every candidate filtered against the whitelist. Nova is pinned to `app.locale` because `lang/vendor/nova` publishes `en` only; the check is `Laravel\Nova\Util::isNovaRequest()`, not a path prefix (a prefix list missed 78 of Nova's 110 routes — see .ai/rules/providers.md).

Notifications persist translation keys, never rendered text, and `Http\Resources\Notification\NotificationResource` ships `message_key` + `message_replace` to the client without calling `__()` — a row outlives the reader's locale. The legacy service rendered server-side and shipped finished sentences; do not go back to that.

## The client catalogue is lang/{locale}.json only — never the translator's loader
The `translations` Inertia prop is built by Actions\Localization\BuildTranslationCatalogue, which reads `lang/{locale}.json` directly after filtering the locale against `petconnect.locales.supported` (it names a file; an unfiltered locale is a read primitive).

Do not "improve" it to `Lang::getLoader()->load($locale, '*', '*')`. FileLoader reduces over jsonPaths + paths, and here jsonPaths includes `lang/vendor/nova`, so `load('en','*','*')` returns 1126 keys — the app's 633 plus Nova's 493 — while `ar` returns 668, because Nova publishes English only. That pushes the whole back-office catalogue into every public page and makes the two locales asymmetric. Same reason the PHP group files under `lang/{locale}/` are excluded: validation/auth/passwords reach the client already rendered inside `errors`.

It is shared as `Inertia::once(...)->as("translations.{$locale}")` in HandleInertiaRequests::shareOnce(), so it rides the initial document only and the locale-keyed once key is what re-sends it on a language switch. Nova requests get `[]`, gated on `Util::isNovaRequest()`. Measured: +29,535 B (en) / +81,662 B (ar) uncompressed on a full visit, ~8.5/12.3 KB gzipped, 0 B on subsequent Inertia visits and partial reloads.
