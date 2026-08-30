<?php

namespace App\Pipelines\Pets\Shared;

use App\Actions\Pets\ResolveMediaOwnerDirectory;
use App\Models\Pet;
use App\Pipelines\Pets\PetAttributeContext;
use Closure;
use Illuminate\Http\UploadedFile;

/**
 * Attach the gallery photos, after the listing itself is committed.
 *
 * Gallery photos share the `pets` collection with the cover photo and are
 * distinguished only by *not* carrying the `featured` custom property. The
 * owner directory is resolved once for the whole batch and copied onto every
 * media row, which is what keeps MediaPathGenerator from looking the owner up
 * per generated URL (see .ai/rules/media-library.md).
 *
 * Uploads run outside the transaction on purpose: a rollback cannot un-write a
 * file, so a failure inside the transaction would leave orphaned uploads on
 * disk with no row pointing at them.
 *
 * The work is inline rather than delegated to an Action of the same name: it
 * has one caller and is not worth exercising without the pipeline.
 */
class AttachGalleryImages
{
    public function __construct(private readonly ResolveMediaOwnerDirectory $resolveOwnerDirectory) {}

    public function handle(PetAttributeContext $context, Closure $next): mixed
    {
        if ($context->galleryImages === []) {
            return $next($context);
        }

        $pet = $context->pet();
        $ownerDirectory = $this->resolveOwnerDirectory->handle($pet);

        foreach ($context->galleryImages as $image) {
            $this->attach($pet, $image, $ownerDirectory);
        }

        return $next($context);
    }

    /**
     * @param  array<string, string>  $ownerDirectory
     */
    private function attach(Pet $pet, UploadedFile $image, array $ownerDirectory): void
    {
        $pet->addMedia($image)
            ->usingFileName($image->hashName())
            ->withCustomProperties($ownerDirectory)
            ->toMediaCollection(Pet::PHOTO_COLLECTION);
    }
}
