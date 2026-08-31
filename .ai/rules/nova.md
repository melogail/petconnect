---
paths:
  - 'app/Nova/**'
---

# Nova

## Nova field rules come from the app's validation Concerns, never restated
A Nova resource that restates its field rules will drift from the application's own, and nothing catches it. `App\Nova\User` had `username: nullable|string|max:255` against `ProfileValidationRules::usernameRules()`'s `alpha_dash|min:3|max:50`, and `locale: required|string` against `Rule::in(config('petconnect.locales.supported'))`. Verified by live PUT: `username = 'not a valid handle!!!'` and `locale = 'klingon'` both persisted. `bio` was `max:2000` against a configured 1000, the lat/lng pair had lost its `required_with`, and the Images field's `singleMediaRules(['image','max:5120'])` dropped the mimes list and doubled `max_avatar_kilobytes`.

A Select's `options()` is not a validator. The docblock claiming a wider Select "would silently write dead values" was right about the risk and wrong about the mechanism — options never reach validation.

`use` the Concern on the resource and call its per-field methods (`$this->usernameRules($this->editedUserId($request))`, `$this->localeRules(required: true)`, `$this->bioRules()`, `$this->avatarFileRules()`). Uniqueness ignore ids come off `$request->resourceId`, not `$this->resource`, so the creation form gets a plain check.

Two shapes to keep: file rules split into a `*FileRules()` method with no `sometimes|nullable`, because a media field validates per file (`ProfileValidationRules::avatarFileRules()`, `App\Concerns\PetPhotoRules::photoFileRules()`); and do not `use` a Concern that carries request-bound public helpers — `PetValidationRules` has `featuredImage()`/`galleryImages()` calling `Request::file()`, which is why the pet photo rules live in their own small trait instead.

## Declare $with = ['media'] on any resource with a media field
`Ebess\AdvancedNovaMediaLibrary\Fields\Images` resolves through `getMedia()` for every row an index renders — one query per row. Nova applies `static::$with` in `PerformsQueries::buildIndexQuery()` and in `ActionRequest`, so declaring it is the whole fix.

Measured on 25 rows: users 26 -> 2 queries, pets 26 -> 2, breeds 26 -> 2, categories 8 -> 2. `Report` was the only resource that set `$with` at all; `User`, `Pet`, `Category` and `Breed` all declare `media` now.

It matters twice over: it also removes the lazy load that the AppServiceProvider Nova exemption was quietly absorbing. Confirmed under test — with `$with` stripped those four indexes still 200 (the exemption catches them) but the query counts jump; with the old broken exemption they 500.

`$with` is index-only. Detail pages are a single `find()`, where `Builder::hydrate()` leaves `preventsLazyLoading` off anyway.
