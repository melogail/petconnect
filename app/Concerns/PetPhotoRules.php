<?php

namespace App\Concerns;

use App\MediaLibrary\ConvertibleImageTypes;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * What an uploaded listing photo has to be, in one place.
 *
 * Split out of PetValidationRules for the same reason
 * ProfileValidationRules::avatarFileRules() is split out of avatarRules(): a
 * back-office uploader validates the file per file rather than as a key on a
 * form bag, so it needs the type, extension and size ceiling without the
 * surrounding optionality.
 *
 * It is a trait of its own rather than a method on PetValidationRules because
 * that trait also carries `featuredImage()` and `galleryImages()`, which call
 * `Illuminate\Http\Request::file()` and are only meaningful on a Form Request.
 * Putting the whole thing on App\Nova\Pet would add two public methods to a
 * Nova resource that would fatal if anything ever called them.
 *
 * That split is also why **both** photo ceilings live here rather than one
 * here and one on PetValidationRules: Web\PetController has to ship them to
 * the listing form as the `photoBounds` prop, and a controller cannot take
 * PetValidationRules without inheriting the two request-only file accessors
 * and a rule set that reads `$this->input()`. `maxGalleryImages()` moved down
 * to join `maxImageKilobytes()` so `photoBounds()` can be built from the same
 * two accessors the `max:` rules are built from, which is what stops the
 * client-side ceiling drifting from the validator.
 */
trait PetPhotoRules
{
    /**
     * The file-shape rules for one listing photo, cover or gallery.
     *
     * The extension list comes from App\MediaLibrary\ConvertibleImageTypes
     * rather than being spelled out here: it is the set the conversion driver
     * can actually decode, and the same source now backs the avatar rules and
     * the taxonomy image rules, so no media field can quietly accept a format
     * that produces no derivative. See that class for what the bare `image`
     * rule lets through.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function photoFileRules(): array
    {
        return ['image', ConvertibleImageTypes::mimesRule(), 'max:'.$this->maxImageKilobytes()];
    }

    /**
     * The bounds a page that renders the listing photo step has to be told.
     *
     * Same arrangement as ReviewValidationRules::reviewBounds(),
     * CommentValidationRules::commentBounds() and
     * MessageValidationRules::messageBounds(): built from the accessors the
     * `max:` rules are built from, so the picker's own cap, the compression
     * target it squeezes a photo down to, and the validator cannot disagree.
     * Both values were hardcoded in `resources/js/pages/pets/{Create,Edit}.vue`
     * — three photos and 512 KB, mirroring config by hand — because nothing
     * shipped them. Shipped by Web\PetController::create and ::edit.
     *
     * Snake_case keys matching the config, following `filterBounds` and
     * `reviewBounds`.
     *
     * @return array{max_gallery_images: int, max_image_kilobytes: int}
     */
    public function photoBounds(): array
    {
        return [
            'max_gallery_images' => $this->maxGalleryImages(),
            'max_image_kilobytes' => $this->maxImageKilobytes(),
        ];
    }

    /**
     * How many gallery photos a listing may carry, excluding the cover photo.
     */
    protected function maxGalleryImages(): int
    {
        return (int) config('petconnect.pets.max_gallery_images', 3);
    }

    /**
     * Per-image upload ceiling in kilobytes.
     */
    protected function maxImageKilobytes(): int
    {
        return (int) config('petconnect.pets.max_image_kilobytes', 512);
    }
}
