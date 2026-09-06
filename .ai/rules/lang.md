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

## The i18n pass runs enum labels first, and owes an accessible-name-in-every-locale check
Ordering, with its reason attached because a bare ordering in a task list gets resequenced by the next person who reads the pass as a flat list of files.

**Enum labels come first, ahead of component strings.** `.ai/rules/enums.md:21` records that every `label()` returns hardcoded English with no `__()`. That gap used to surface only on pet-detail and profile pages; it now renders on the **public feed, to guests** — `HomeController` ships `ReportCategory::options()` and `ReportReason::options()` on every feed response — so an Arabic-reading visitor meets "Hate Speech" and "Copyright" in English on the first screen they see. Reach is the reason for the ordering, not effort or tidiness.

**Explicit deliverable of the same pass: an accessible name must remain a superset of its trigger's visible text in EVERY locale, not only in English.** This is stronger and more interesting than the English-only form, because a translator can break it without touching code — speech input matches on visible text, so a name that stops containing it stops being addressable (WCAG 2.5.3).

Worked instance, established by reading. `resources/js/components/messaging/StartConversationButton.vue` renders hardcoded English "Message" as the visible text of the `<Button>` inside its `DialogTrigger` — **cited by element rather than by line, because the `:88` that stood here was wrong: the literal is at `:134` on 2026-09-06.** Re-grep `DialogTrigger`; do not trust either number. (Five wrong line citations across four claims were found in one sweep of this directory, and every one of them already named an identifier in the same sentence — see "A claim must say how it was established" in `.ai/rules/general.md`.)

`messaging.send_message` → "Send Message" contains "Message" and satisfies 2.5.3; `pets.contact_owner` → "Contact Owner" does not. **Cited by key, not by line:** both keys exist in both catalogues, at the same line number in each (`messaging.send_message` in `lang/en.json` and `lang/ar.json`; `pets.contact_owner` likewise) — the earlier `en.json:518` / `ar.json:161` pairing read as though each key lived in only one file. The feed honours the containment property and the detail page did not, which is what made it a defect rather than a preference.

**The Arabic half is already open and this pass is what owes it.** Under `ar`, `messaging.send_message` is "إرسال الرسالة", which contains no "Message", so the owner card's trigger fails 2.5.3 for an Arabic reader **today** — measured on `/pets/10` under `dir="rtl"` in an isolated build on 2026-09-06, where the two triggers on that page render the identical visible string "Message" while computing to `Message Catharine Zulauf about Mose` (contains it) and `إرسال الرسالة` (does not). The component's own docblock records this and says the fix is to translate **the trigger's visible text**, not to invent an English-shaped key at a call site. Check containment per locale, not once.
