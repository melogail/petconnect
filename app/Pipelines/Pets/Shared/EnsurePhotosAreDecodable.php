<?php

namespace App\Pipelines\Pets\Shared;

use App\Exceptions\Pets\PetPhotoNotDecodable;
use App\MediaLibrary\ImageDecodeVerifier;
use App\Pipelines\Pets\PetAttributeContext;
use Closure;

/**
 * Refuse a submitted photo the conversion driver cannot read.
 *
 * The Form Request's `image` and `mimes:` rules both decide on the sniffed mime
 * type, so a file with a genuine JPEG header and padding behind it reaches the
 * flow intact. Without this step the failure surfaces inside `addMedia()`, i.e.
 * after the listing row, the media row and the stored original are all
 * committed: a 500, an orphan media row, a file on the public disk, and a
 * `display` URL on the listing pointing at a conversion that was never written
 * (`PetMediaResource` reads `getUrl('display')`, which does not fall back to
 * the original).
 *
 * It therefore runs before the persist step in both flows, so a rejected upload
 * leaves nothing behind at all — and in the update flow before
 * ReplaceFeaturedImage, which deletes the existing cover photo before attaching
 * the new one.
 *
 * This is not the whole fix and is not meant to be: it covers the request path
 * only. A conversion left queued still runs later with no request to answer,
 * which is what App\MediaLibrary\TemporaryDirectoryCleaningFileManipulator is
 * for.
 *
 * The passable is the shared PetAttributeContext because create and update
 * submit photos identically. The step reads no configuration: the driver the
 * decode is attempted with belongs to the verifier it is handed.
 *
 * @throws PetPhotoNotDecodable
 */
class EnsurePhotosAreDecodable
{
    public function __construct(private readonly ImageDecodeVerifier $verifier) {}

    public function handle(PetAttributeContext $context, Closure $next): mixed
    {
        if ($context->featuredImage !== null && ! $this->verifier->canDecode($context->featuredImage)) {
            throw PetPhotoNotDecodable::forFeaturedImage();
        }

        foreach ($context->galleryImages as $index => $image) {
            if (! $this->verifier->canDecode($image)) {
                throw PetPhotoNotDecodable::forGalleryImage((int) $index);
            }
        }

        return $next($context);
    }
}
