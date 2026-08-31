<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validation rules for everything that writes a user's own record.
 *
 * Two entry points, deliberately different sizes:
 *
 * - profileRules() is the identity pair — name and email — and is what
 *   registration validates (Actions\Fortify\CreateNewUser, which cannot use a
 *   Form Request because Fortify's CreatesNewUsers contract hands it an array).
 * - profileFormRules() is the settings form: profileRules() plus the contact,
 *   location, language and avatar fields. It does **not** carry a password
 *   change; see below.
 *
 * ## A profile save is a PATCH, and that is not the pet form's arrangement
 *
 * Every optional key here is `sometimes|nullable`, never `present|nullable`.
 * The single exception is the `lat`/`lng` pair, which is `nullable` with no
 * `sometimes` because `sometimes` would gate off its own `required_with` — see
 * latitudeRules() for the measurement and .ai/rules/concerns.md for the trap.
 * .ai/rules/requests.md requires `present` on the scalar keys of a write bag
 * *that is written whole* — PUT on a pet is a full replacement, so an omitted
 * key there has to be a 422 rather than a silent wipe. A profile save is a
 * PATCH: Pipelines\Profiles\UpdateProfile\PersistProfileAttributes fills only
 * the keys the request actually sent, so an omitted key is "I did not touch
 * that field" and writing null would be wrong.
 *
 * That choice is not free, and the cost is the same trap in a different
 * direction: renaming a key here without renaming it in
 * Http\Resources\Profile\ProfileFormResource makes the field stop saving,
 * silently, instead of 422ing. The resource<->Form-Request key-parity test is
 * the guard, and the two files have to be edited together — the same standing
 * warning the pet form carries, which is why the rules live in a Concern that
 * says so rather than inline in the request.
 *
 * `image` is not part of the persisted attribute bag at all — the pipeline's
 * UploadProfileImage step consumes it — so it carries no `present` for a
 * second, independent reason.
 *
 * ## Changing a password is not this form's job, and used to be
 *
 * This trait carried `current_password` and `password` rules, and
 * Actions\Profiles\UpdateProfile ran a VerifyCurrentPassword and a
 * HashNewPassword step for them, while `settings/Security` changed the same
 * credential through Fortify's `user-password.update`. Two endpoints, two
 * validation paths and two error vocabularies for one outcome. **This form no
 * longer accepts either key**, and `user-password.update` is the single path.
 *
 * Both rules are gone rather than deprecated, and the two pipeline steps and
 * App\Exceptions\Profiles\IncorrectCurrentPassword are deleted, because a rule
 * with no route behind it is worse than no rule: a client would keep posting a
 * pair that validated and did nothing.
 *
 * Three reasons the dedicated endpoint wins. It is the target starter kit's own
 * scaffolding and already has its own tests. It keeps a credential change out
 * of a multipart form that also uploads an image, where a failed avatar
 * conversion and a failed password proof would share one error bag. And it can
 * sit behind its own throttle and its own `RequirePassword` treatment without
 * imposing either on somebody editing their bio.
 *
 * **This is a deliberate divergence from the legacy app**, which put the
 * password change on the profile form. It is a behaviour change, not a port:
 * the field moves to `settings/security`, which is where the rest of the
 * account's credentials — two-factor, passkeys — already live.
 *
 * ## `email` and `username` are unique, so the rule needs the current id
 *
 * Both `Rule::unique()` calls take an ignore id, which the Form Request passes
 * from `$this->user()->id`. Called with null (registration) they are plain
 * uniqueness checks.
 */
trait ProfileValidationRules
{
    /**
     * The identity fields every user record needs: name and email.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($userId),
        ];
    }

    /**
     * The full settings form.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileFormRules(?int $userId = null): array
    {
        return [
            ...$this->profileRules($userId),
            'username' => $this->usernameRules($userId),
            'phone' => $this->phoneRules(),
            'bio' => $this->bioRules(),
            'address' => $this->addressLineRules(),
            'city' => $this->addressLineRules(),
            'state' => $this->addressLineRules(),
            'country' => $this->addressLineRules(),
            'lat' => $this->latitudeRules(),
            'lng' => $this->longitudeRules(),
            'timezone' => $this->timezoneRules(),
            'locale' => $this->localeRules(),
            'image' => $this->avatarRules(),
        ];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * The contact number. Free text: the column takes any international
     * shape and no part of the application parses it.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function phoneRules(): array
    {
        return ['sometimes', 'nullable', 'string', 'max:255'];
    }

    /**
     * The self-description, bounded by `petconnect.profiles.bio_max_length`
     * rather than by a literal — the column is `text`, so this config value is
     * the only ceiling there is.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function bioRules(): array
    {
        return ['sometimes', 'nullable', 'string', 'max:'.$this->bioMaxLength()];
    }

    /**
     * One line of a postal address: `address`, `city`, `state` or `country`.
     * All four are plain `varchar(255)` columns with no lookup behind them.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function addressLineRules(): array
    {
        return ['sometimes', 'nullable', 'string', 'max:255'];
    }

    /**
     * The IANA zone, checked against PHP's own list by the `timezone` rule.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function timezoneRules(): array
    {
        return ['sometimes', 'nullable', 'string', 'timezone', 'max:255'];
    }

    /**
     * Latitude, paired with longitude.
     *
     * `required_with` in both directions is what stops half a coordinate being
     * written: a lat with no lng puts the member nowhere Pet::nearby() can
     * find, and the column pair is read as one value everywhere.
     *
     * **These two are the one exception to the `sometimes|nullable` shape the
     * rest of this trait uses, and it is not a style choice.** `sometimes`
     * gates the *whole attribute*: Validator::isValidatable() calls
     * passesOptionalCheck(), which returns false the moment an attribute
     * carrying `sometimes` is absent, and that skips every rule on it —
     * implicit ones like `required_with` included. Paired with `sometimes`,
     * `required_with` could therefore only fire when both keys were already on
     * the wire, which is exactly the case it is not needed for. Measured: a
     * PATCH of `lat` alone passed and wrote `lat = 51.5, lng = null`, and the
     * same payload through App\Nova\User returned 200 and wrote the same half
     * coordinate. It is the third instance of this trap in this codebase; see
     * the `sometimes` section of .ai/rules/concerns.md.
     *
     * `nullable` alone carries the optionality with no loss: an absent key
     * still skips `numeric` and `between` (presentOrRuleIsImplicit), and
     * validated() still omits it, because validated() decides on a
     * `$missingValue` sentinel and never consults `sometimes`. PATCH semantics
     * are unaffected — PersistProfileAttributes still only writes keys the
     * request sent.
     *
     * `required_with` is ordered ahead of the rules that inspect the value so
     * the missing-pair message wins when both could fire.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function latitudeRules(): array
    {
        return ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'];
    }

    /**
     * Longitude, paired with latitude. See latitudeRules(), which carries the
     * whole reason neither of these may take a `sometimes`.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function longitudeRules(): array
    {
        return ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'];
    }

    /**
     * The public handle. Nullable, because the column is and because every
     * account created before the profile form existed has none.
     *
     * `alpha_dash` keeps it URL-safe. That is a property worth having even
     * though **`username` is not a route key and must not become one**:
     * User::getRouteKeyName() stays `id`, because App\Enums\Reviewable and
     * Reportable resolve their targets through resolveRouteBinding(), and a
     * User keyed on `username` would have every one of those comparisons match
     * an integer id against a string column.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function usernameRules(?int $userId = null): array
    {
        return [
            'sometimes',
            'nullable',
            'string',
            'alpha_dash',
            'min:3',
            'max:50',
            $userId === null
                ? Rule::unique(User::class, 'username')
                : Rule::unique(User::class, 'username')->ignore($userId),
        ];
    }

    /**
     * The language field.
     *
     * Optional on the profile form, which may not even render a language
     * control; required on Http\Requests\Profile\UpdateLocaleRequest, whose
     * only purpose is to change it. Same whitelist either way —
     * `petconnect.locales.supported`, which is also what
     * Actions\Profiles\ApplyUserLocale falls back from and what the SetLocale
     * middleware filters its candidates against.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function localeRules(bool $required = false): array
    {
        /** @var list<string> $supported */
        $supported = config('petconnect.locales.supported', ['en']);

        return $required
            ? ['required', 'string', Rule::in($supported)]
            : ['sometimes', 'nullable', 'string', Rule::in($supported)];
    }

    /**
     * The avatar upload.
     *
     * `image` is the file input's name on the write side. The read side calls
     * it `avatar` and emits a URL — different shapes, different key names, per
     * .ai/rules/resources.md, exactly as the pet form's `images` / `photos`
     * split works.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function avatarRules(): array
    {
        return ['sometimes', 'nullable', ...$this->avatarFileRules()];
    }

    /**
     * What the uploaded file itself has to be, with no optionality attached.
     *
     * Split out because a back-office uploader validates the file per file
     * rather than as a key on a PATCH bag: App\Nova\User hands this array to
     * the media field's singleMediaRules(), which applies it to each file the
     * admin drops. Keeping the type, extension and size ceiling here means the
     * admin path and the member path cannot disagree about what an avatar is.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function avatarFileRules(): array
    {
        return [
            'image',
            'mimes:jpg,jpeg,png,gif,webp',
            'max:'.$this->maxAvatarKilobytes(),
        ];
    }

    protected function bioMaxLength(): int
    {
        return (int) config('petconnect.profiles.bio_max_length', 1000);
    }

    protected function maxAvatarKilobytes(): int
    {
        return (int) config('petconnect.profiles.max_avatar_kilobytes', 2048);
    }
}
