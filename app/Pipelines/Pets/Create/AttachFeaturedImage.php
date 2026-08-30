<?php

namespace App\Pipelines\Pets\Create;

use App\Actions\Pets\AttachFeaturedImage as AttachFeaturedImageAction;
use Closure;

/**
 * Attach the cover photo, after the listing itself is committed.
 *
 * Uploads run outside the transaction on purpose: a rollback cannot un-write a
 * file, so a failure inside the transaction would leave orphaned uploads on
 * disk with no row pointing at them.
 *
 * Delegates rather than inlines because the Action has a second caller: the
 * update flow's ReplaceFeaturedImage attaches the same way after deleting the
 * old cover photo.
 */
class AttachFeaturedImage
{
    public function __construct(private readonly AttachFeaturedImageAction $attachFeaturedImage) {}

    public function handle(CreatePetContext $context, Closure $next): mixed
    {
        if ($context->featuredImage === null) {
            return $next($context);
        }

        $this->attachFeaturedImage->handle($context->pet(), $context->featuredImage);

        return $next($context);
    }
}
