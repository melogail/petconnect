---
paths:
  - 'app/Http/Requests/Profile/**'
---

# Profile

## The profile form is a PATCH; the pet form is a PUT — the `present` rule differs because of it
`.ai/rules/requests.md` requires `present` alongside `nullable` on every scalar key of a write bag. That rule is scoped to bags written **whole**: `pets.update` is a full replacement, so an omitted key must 422 rather than wipe the column.

`profile.update` is the opposite and must stay that way. Every optional key in `App\Concerns\ProfileValidationRules::profileFormRules()` is `sometimes|nullable`, and `Pipelines\Profiles\UpdateProfile\PersistProfileAttributes` fills only the keys the request sent. The form is edited a section at a time (account panel, location panel, language control), so full-replacement semantics would let a form rendering half the fields wipe the other half — and adding `present` would 422 the existing minimal `name` + `email` save.

The cost is real and is the reason this is written down: a key renamed in the Concern but not in `Http\Resources\Profile\ProfileFormResource` **stops saving silently** instead of 422ing. The resource↔Form-Request key-parity test is the only guard. Edit the two together.

Two more shape notes for this directory:
- Read and write names differ where the shapes differ: `avatar` (URL, read) vs `image` (file, write), the same split as the pet form's `photos`/`images`.
- Do not name a FormRequest accessor `image()` — `Illuminate\Http\Request::image(string $key): ?Illuminate\Image\Image` exists in Laravel 13 and an incompatible override is a fatal at class load, taking the whole application down rather than one endpoint. It is `uploadedImage()`.

## Password changes are Fortify's user-password.update only — profile.update does not accept them
Settled in Phase 4a, and a **deliberate divergence from the legacy app**, which put the password change on the profile form.

`profile.update` used to accept `current_password` / `password` while `settings/Security` posted the same change to Fortify's `user-password.update` — one outcome, two endpoints, two validation paths, two error vocabularies. Fortify's is now the single path: it is the starter kit's own scaffolding, it has its own tests and throttle, and it keeps a credential change out of a multipart form that also uploads an avatar.

Removed, not deprecated: `ProfileValidationRules::passwordChangeRules()`, the `VerifyCurrentPassword` and `HashNewPassword` pipeline steps, `App\Exceptions\Profiles\IncorrectCurrentPassword`, and the `currentPassword()` / `newPassword()` accessors on `UpdateProfileRequest`. A rule with no route behind it is worse than no rule — a client would keep posting a pair that validated and did nothing.

Consequences: `UpdateProfileContext` carries nothing credential-shaped, `UpdateProfile::handle()` takes `(user, attributes, image)` only, and `PersistProfileAttributes::NON_ATTRIBUTE_KEYS` is `['image']`. Do not add the pair back to this form.

## `user-password.update` is this application's route, not Fortify's — correcting the note above
The section above is right that `profile.update` must not accept a password change and that `settings/password` is the single path. It is wrong about whose path that is, and the wording ("Fortify's is now the single path: it is the starter kit's own scaffolding, it has its own tests and throttle") would send the next person to delete a route they think is duplicated.

Verified against laravel/fortify 1.39.0: `Laravel\Fortify\Actions\UpdateUserPassword` **does not exist** — it was only ever a published starter-kit stub. Only the marker contract `UpdatesUserPasswords` ships, nothing binds it, and `config/fortify.php` does not enable `Features::updatePasswords()`, so Fortify's `PUT /user/password` and its `PasswordController` are never registered. The route named `user-password.update` is declared in `routes/settings.php` and points at `Settings\SecurityController::update`. The name is inherited, the implementation is ours.

So the route, `PasswordUpdateRequest`, the `auth` + `verified` + `throttle:6,1` middleware, the toast and the default error bag are all this application's to keep. Do not "move it to Fortify" — there is nothing to move it to. Do not switch to `validateWithBag('updatePassword')` the way Fortify's stub does: `resources/js/pages/settings/Security.vue` reads `errors.current_password` off the default bag.

The write is `Actions\Profiles\UpdatePassword::handle(User $user, string $password)`, not the controller. It also calls `Password::broker(config('fortify.passwords'))->deleteToken($user)` — the one thing Fortify's controller does that this app did not. Nothing else in the codebase calls `deleteToken`, so before this a user who requested a reset link and then changed their password from settings left the emailed link live for its full TTL, in a mailbox that may be exactly what prompted the change. Any future path that sets a password owes the same call.
