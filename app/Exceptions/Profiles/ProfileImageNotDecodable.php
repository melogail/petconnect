<?php

namespace App\Exceptions\Profiles;

use Illuminate\Validation\ValidationException;

/**
 * A submitted avatar passed every header check and cannot be decoded.
 *
 * `image` and `mimes:` both decide on the sniffed mime type, so a file that
 * carries a real JPEG header and nothing usable behind it clears
 * `App\Concerns\ProfileValidationRules::avatarFileRules()` and only fails when
 * a conversion asks the driver for the pixels. Both of `User`'s conversions are
 * `nonQueued()`, so that happens synchronously inside `addMedia()` — i.e.
 * inside `Pipelines\Profiles\UpdateProfile\UploadProfileImage`, inside the
 * transaction `Actions\Profiles\UpdateProfile` opens — and escapes as a 500
 * having already copied the original onto the disk.
 * `UpdateProfile\EnsureProfileImageIsDecodable` throws this before anything is
 * written instead.
 *
 * It extends ValidationException for the same reason
 * `Exceptions\Pets\PetPhotoNotDecodable` and `PetTaxonomyNotFound` do: this
 * genuinely is a field-level input problem — the user picked a broken file and
 * has to pick another one — so Laravel's existing 422-with-`errors` /
 * redirect-back-with-errors handling is exactly the behaviour wanted, and a
 * bespoke exception plus a `render` mapping would be two more moving parts for
 * the same bytes. See .ai/rules/pipelines.md for when a domain abort may use
 * this base and when it may not.
 */
class ProfileImageNotDecodable extends ValidationException
{
    /**
     * The avatar could not be decoded.
     *
     * Keyed on `image`, **not** `avatar`. `image` is the file input's name on
     * the write side and the key `ProfileValidationRules::profileFormRules()`
     * validates the upload under; `avatar` is the read side's URL key on
     * `ProfileFormResource` and no request bag or error bag ever carries it.
     * Keying this on `avatar` would put the message on a control the form does
     * not render as an input. See .ai/rules/profile.md for the read/write key
     * split.
     */
    public static function forAvatar(): self
    {
        return self::withMessages([
            'image' => __('That image could not be read. Please choose a different photo.'),
        ]);
    }
}
