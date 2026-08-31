<?php

namespace App\Observers;

use App\MediaLibrary\MediaPathGenerator;
use App\MediaLibrary\OwnerDirectoryResolver;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Stamps MediaPathGenerator::OWNER_DIRECTORY_PROPERTY onto every media row that
 * arrives without one, so the stored path can be built from the row alone.
 *
 * ## Why this is an observer and not another `withCustomProperties()` call
 *
 * The property is what keeps MediaPathGenerator from having to look the owner
 * up: without it the generator falls back to two queries per owner per request
 * — on the public listing page, for every card — and its own docblock says that
 * fallback is not to be relied on.
 *
 * The application's own upload paths set it (UpdateProfile\UploadProfileImage
 * and Actions\Pets\ResolveMediaOwnerDirectory), but *every other* writer has to
 * remember to, and Ebess\AdvancedNovaMediaLibrary\Fields\Media::fillMedia calls
 * `->withCustomProperties($this->customProperties)` with whatever the field was
 * configured with — empty for all four of our media fields. So every avatar or
 * pet photo an admin uploaded through Nova was permanently missing it. Adding
 * `withCustomProperties()` to those four fields would fix today's four call
 * sites and nothing about the fifth. A `creating` hook is the only place that
 * covers a path nobody has written yet.
 *
 * ## Why `creating`
 *
 * MediaCollections\FileAdder::processMediaItem() saves the media row and *then*
 * hands it to the filesystem, which is where getPath() is first asked. The
 * morph columns are already set by then — MorphMany::save() fills them before
 * the insert — so `creating` is both late enough to resolve the owner and early
 * enough that the property is in the row the path is generated from, with no
 * second UPDATE.
 *
 * A global model (Category, Breed) has no owner. The resolver answers null for
 * those without a query and nothing is stamped, which is the same thing the
 * generator concludes.
 */
class MediaOwnerDirectoryObserver
{
    public function __construct(private readonly OwnerDirectoryResolver $ownerDirectories) {}

    /**
     * Fill in the owner directory the uploader did not supply.
     */
    public function creating(Media $media): void
    {
        $stored = $media->getCustomProperty(MediaPathGenerator::OWNER_DIRECTORY_PROPERTY);

        if (is_string($stored) && $stored !== '') {
            return;
        }

        $directory = $this->ownerDirectories->lookUp((string) $media->model_type, $media->model_id);

        if ($directory === null) {
            return;
        }

        $media->setCustomProperty(MediaPathGenerator::OWNER_DIRECTORY_PROPERTY, $directory);
    }
}
