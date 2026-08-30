---
paths:
  - 'app/Enums/**'
---

# Enums

## Enums: snake_case backing values, TitleCase cases, and morph-alias whitelists
Backing values are machine-safe lowercase snake_case (`hate_speech`, `pending`, `adoption`); human text comes from `label()`. Never store display strings in the DB — the legacy enums did and it is deliberately not ported. Case names are TitleCase, backing type is always explicit `: string` (ListingType was int-backed in legacy while its column was a string — fixed).

`Commentable`, `Reportable`, `Reviewable` are the whitelists for the polymorphic `*_type` inputs. Controllers/Form Requests must resolve a morph target through the enum's `modelClass()` / `findOrFail()`, never from a raw class name in the request or URL. Their backing values are exactly the morph map aliases registered in `AppServiceProvider::configureMorphMap()` — keep the two in sync.
