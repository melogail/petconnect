<?php

namespace App\Concerns;

use App\MediaLibrary\ConvertibleImageTypes;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * What a category or breed image has to be, in one place.
 *
 * The taxonomy is administered in Nova and nowhere else, so unlike listing
 * photos and avatars there is no member-facing form to keep in step — which is
 * exactly how `App\Nova\Breed` and `App\Nova\Category` came to validate their
 * media field with a bare `['image', 'max:5120']` restated on each resource.
 * `image` is wider than the conversion driver: it admits `bmp`, `heic` and
 * `heif`, GD reads none of them, and a conversion that never runs means
 * `getFirstMediaUrl('thumb')` silently serves the multi-megabyte original in
 * place of a 160px crop. The extension list therefore comes from
 * `App\MediaLibrary\ConvertibleImageTypes`, the same source
 * `PetPhotoRules::photoFileRules()` and
 * `ProfileValidationRules::avatarFileRules()` now read, so the four media
 * fields cannot drift apart again.
 *
 * Shaped as a `*FileRules()` method carrying no `sometimes|nullable`, like the
 * other two: a media field validates per file rather than as a key on a form
 * bag (.ai/rules/nova.md).
 */
trait TaxonomyImageRules
{
    /**
     * The file-shape rules for one category or breed image.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function taxonomyImageFileRules(): array
    {
        return ['image', ConvertibleImageTypes::mimesRule(), 'max:'.$this->maxTaxonomyImageKilobytes()];
    }

    /**
     * Per-file upload ceiling for a taxonomy image, in kilobytes.
     *
     * 5120 is the ceiling both Nova fields already carried, kept deliberately:
     * narrowing the accepted formats is the defect being fixed here, and moving
     * an admin's size limit in the same change would be a second, unannounced
     * behaviour change.
     *
     * It is a literal rather than a `config('petconnect.taxonomy.*')` read, the
     * way `maxImageKilobytes()` and `maxAvatarKilobytes()` are: those keys
     * exist, this one does not, and referring to a key that is only ever
     * satisfied by its own default is a phantom every reader has to check.
     * Adding it is a change to `config/petconnect.php`, which belongs with
     * whoever owns that file — this stays the one place the number is written
     * either way.
     */
    protected function maxTaxonomyImageKilobytes(): int
    {
        return 5120;
    }
}
