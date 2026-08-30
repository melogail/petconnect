<?php

namespace App\Exceptions\Pets;

use Illuminate\Validation\ValidationException;

/**
 * An edit would push a listing past its lifetime photo allowance.
 *
 * `images` is capped per request by the Form Request, which bounds one upload
 * but not the total: repeated edits each uploading the maximum accumulated
 * without any ceiling. This is the lifetime cap, so it has to be decided
 * against what is already attached, which the Form Request has no business
 * querying — the update pipeline throws this instead.
 *
 * It extends ValidationException so Laravel renders it as a field error on
 * `images`, the control the user can actually act on, rather than as a 500.
 */
class PetGalleryLimitExceeded extends ValidationException
{
    public static function withCounts(int $attempted, int $allowed): self
    {
        return self::withMessages([
            'images' => __('This listing can hold :allowed additional images; the edit would leave it with :attempted.', [
                'allowed' => $allowed,
                'attempted' => $attempted,
            ]),
        ]);
    }
}
