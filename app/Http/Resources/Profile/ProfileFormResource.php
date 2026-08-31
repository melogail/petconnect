<?php

namespace App\Http\Resources\Profile;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The account holder's own record, as the settings form reads it back.
 *
 * **Private payload.** It carries the email, phone, street address and exact
 * coordinates, and it is served from `profile.edit` only, which sits behind
 * `auth` and acts on `$request->user()`. Http\Resources\Profile\ProfileResource
 * is the public page's payload and deliberately carries none of them.
 *
 * ## Key parity with the Form Request is the contract
 *
 * Every key below except `id`, `avatar` and `is_verified` has a rule of exactly
 * that name in App\Concerns\ProfileValidationRules::profileFormRules(), because
 * the form posts this object back. Rename a key in one and it must be renamed
 * in the other **in the same change** — .ai/rules/resources.md, and the same
 * trap that silently wiped seven pet fields once.
 *
 * The failure mode here is quieter than the pet form's, and that is deliberate:
 * a profile save is a PATCH, so a renamed key stops saving rather than being
 * written as null. There is no `present` guard to make it loud, because
 * `present` on a partial-update form would 422 every save that renders only
 * half the fields. The resource<->Form-Request key-parity test is what catches
 * it.
 *
 * ## Where the read and write shapes differ, they have different names
 *
 * - `avatar` is a URL and is read-only; the upload key is **`image`**, a file.
 *   Same split as the pet form's `photos` (read) versus `images` (write), and
 *   for the same reason: a client that posted back what it received would send
 *   a URL at a file rule and 422. See .ai/rules/resources.md.
 * - `current_password`, `password` and `password_confirmation` are not on
 *   either side any more. Changing a password is Fortify's
 *   `user-password.update` and only that: this form no longer accepts the pair
 *   at all. See App\Concerns\ProfileValidationRules for the decision and what
 *   it diverges from.
 * - `is_active` appears on neither side. It is absent from User's #[Fillable]
 *   and from the rules, because deactivation is a moderation decision on the
 *   `admins` guard, not a checkbox on the owner's own form (see
 *   Http\Middleware\EnsureAccountIsActive).
 *
 * ## `lat` / `lng` are `string|float|null`, and that is driver-dependent
 *
 * They are emitted exactly as the model hands them over, with no cast and no
 * formatting. `users.lat` is `decimal(10, 8)` and `users.lng` is
 * `decimal(11, 8)`, so **the PHP type is whichever the driver returns**: MySQL
 * returns DECIMAL as a string, SQLite gives the column NUMERIC affinity and
 * PDO returns a float (measured as `double` on the test connection). The
 * `@property string|null` on User and the earlier claim here that these are
 * "decimal strings, not floats" were both only half true, and the half that was
 * false is the one a typed client would have been built on.
 *
 * A `decimal:8` cast would make it a string everywhere and was considered and
 * **declined**, for two reasons. It is a formatting cast: it would emit
 * `"31.20000000"` where the row holds `31.2`, so the form would post back a
 * differently spelled value than it was given — the exact round-trip drift the
 * old note claimed to be avoiding. And it would fix one of two coordinate pairs:
 * `pets.latitude` / `pets.longitude` are the same uncast decimal columns and
 * Http\Resources\Pet\PetDetailResource emits them the same way, so casting
 * here alone would give the frontend two coordinate shapes to reason about
 * instead of one. If this is ever tightened, tighten both in one change.
 *
 * Nothing downstream is broken by the ambiguity: `numeric` accepts either, and
 * `JSON.stringify` of a float and of a numeric string both survive the round
 * trip. A client should widen to `number | string | null` and coerce once.
 *
 * The avatar is read with getFirstMediaUrl(), so the caller must eager load
 * `media`.
 *
 * ## `created_at` is gone
 *
 * It was emitted here and read by nobody. A join date is a fact about the
 * *public* person rather than a field of the account form, and
 * Http\Resources\Profile\ProfileResource already carries it for the page that
 * displays it; there is nothing a settings form does with the value that the
 * profile page does not do better. Removing it keeps this payload to "the
 * fields you are editing, plus the three read-only ones the form needs to
 * render them" — which is also what makes the key-parity test's read-only
 * exemption list short enough to be worth reading.
 *
 * ## Nothing renders this yet — the settings UI is Phase 4's
 *
 * Read this before treating the 16 keys below as a live contract. No `.vue`
 * file was touched in the phase that built them: `resources/js/pages/settings/
 * Profile.vue` still renders `name` and `email` only, and it reads them from
 * `page.props.auth.user`, not from this resource or from the `locales` prop
 * beside it. Both props are served by Settings\ProfileController::edit and
 * consumed by nobody.
 *
 * That is not a bug and needs no unwinding. Every new rule in
 * App\Concerns\ProfileValidationRules is `nullable` and the save is a PATCH,
 * so the existing two-field form keeps working untouched. But a reader who
 * assumes the fields below are on screen will misjudge what a rename breaks —
 * today it breaks a test, not a page. `profile/Show.vue` is missing the same
 * way, and `messaging/Index.vue` / `messaging/Show.vue` have been outstanding
 * since Phase 2d: four page components in total, all Phase 4's.
 *
 * @mixin User
 */
class ProfileFormResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     username: string|null,
     *     email: string,
     *     phone: string|null,
     *     bio: string|null,
     *     address: string|null,
     *     city: string|null,
     *     state: string|null,
     *     country: string|null,
     *     lat: string|float|null,
     *     lng: string|float|null,
     *     timezone: string|null,
     *     locale: string,
     *     avatar: string|null,
     *     is_verified: bool
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'bio' => $this->bio,

            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'timezone' => $this->timezone,

            'locale' => $this->locale,

            'avatar' => $this->getFirstMediaUrl('users', 'display') ?: null,
            'is_verified' => $this->isVerified(),
        ];
    }
}
