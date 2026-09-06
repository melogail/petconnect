<?php

namespace App\Exceptions\Pets;

use Illuminate\Validation\ValidationException;

/**
 * A submitted listing photo passed every header check and cannot be decoded.
 *
 * `image` and `mimes:` both decide on the sniffed mime type, so a file that
 * carries a real JPEG header and nothing usable behind it clears the Form
 * Request and only fails when a conversion asks GD for the pixels — after the
 * media row and the original file are committed, as a 500 with an orphan row
 * and a listing whose `display` URL points at a conversion that was never
 * written. `Pipelines\Pets\Shared\EnsurePhotosAreDecodable` throws this before
 * anything is written instead.
 *
 * It extends ValidationException for the same reason PetTaxonomyNotFound and
 * PetGalleryLimitExceeded do: this genuinely is a field-level input problem —
 * the user picked a broken file and has to pick another one — so Laravel's
 * existing 422-with-`errors` / redirect-back-with-errors handling is exactly
 * the behaviour wanted, and a bespoke exception plus a `render` mapping would
 * be two more moving parts for the same bytes. See .ai/rules/pipelines.md for
 * when a domain abort may use this base and when it may not.
 */
class PetPhotoNotDecodable extends ValidationException
{
    /**
     * The cover photo could not be decoded.
     *
     * Keyed on `featuredImage`, the key PetValidationRules::imageRules()
     * validates it under, so the message lands on the control that uploaded it.
     */
    public static function forFeaturedImage(): self
    {
        return self::withMessages([
            'featuredImage' => __('That image could not be read. Please choose a different photo.'),
        ]);
    }

    /**
     * A gallery photo could not be decoded.
     *
     * Keyed on `images.{index}` to match `images.*`. The index is the position
     * within the uploads the Form Request handed the flow, which is the same
     * position the client posted unless it sent something that was not a file
     * at all — in which case `images.*` has already rejected the request.
     */
    public static function forGalleryImage(int $index): self
    {
        return self::withMessages([
            "images.{$index}" => __('That image could not be read. Please choose a different photo.'),
        ]);
    }
}
