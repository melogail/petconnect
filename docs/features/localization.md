# Localization

English and Arabic, selectable per member, with right-to-left layout for Arabic.

## Resolution order

`SetLocale` middleware decides the locale for each request:

1. The authenticated member's `users.locale`
2. The plaintext `locale` cookie (web) or the `X-Locale` header (API)
3. `APP_LOCALE` from `.env`

`POST locale` (`locale-switches`, IP-keyed because guests can call it) stores the choice in the cookie and, when signed in, on the user.

## What the client receives

`HandleInertiaRequests` shares:

```json
"locale": { "current": "ar", "direction": "rtl", "supported": ["en", "ar"] },
"translations": { "...": "..." }
```

`translations` is an `Inertia::once()` prop: the whole catalogue for the current locale is sent on the first visit of a session and reused afterwards. The `locale` cookie is unencrypted so `app.ts` can set `<html lang dir>` before the first payload arrives, avoiding a layout flash.

## Files

| File | Holds |
| --- | --- |
| `lang/en.json`, `lang/ar.json` | Every UI string, keyed by the English sentence |
| `lang/en/*.php`, `lang/ar/*.php` | Framework strings: auth, pagination, passwords, validation |
| `categories.name_ar`, `description_ar`; breeds likewise | Taxonomy labels |
| Enum `label()` methods | Translated labels for every backed enum |

`tests/Feature/TranslationCatalogueParityTest.php` fails when a key exists in one JSON file and not the other. Add both sides in the same change.

## Typography and layout

Readex Pro is loaded with the Arabic subset for every Arabic role; Manrope and Newsreader cover Latin UI and headings. Tailwind's logical properties (`ms-`, `pe-`, `start-`) are used instead of left/right so components flip correctly under `dir="rtl"`.

## Mobile

The Flutter app sends `X-Locale` and keeps its own `en`/`ar` catalogues, mirrored from the web strings (around 95 keys for the listing form alone). `/api/v1/options` returns enum labels already translated for the requested locale.

## Known gaps

- `APP_LOCALE` defaults to `en`; the target market is Arabic-first and the default has not been switched yet.
- Some wizard and messaging strings have been reported missing Arabic coverage in the past; the parity test now guards the JSON catalogues but not Vue-side hard-coded text.
