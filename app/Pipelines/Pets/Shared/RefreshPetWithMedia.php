<?php

namespace App\Pipelines\Pets\Shared;

use App\Pipelines\Pets\PetAttributeContext;
use Closure;

/**
 * Reload the listing so the caller receives it with its freshly attached
 * photos, rather than with the media relation as it stood before the upload
 * steps ran.
 *
 * refresh() already re-runs every relation that is loaded, so the follow-up is
 * loadMissing() rather than load(): load() would query the relations refresh()
 * had just fetched a second time.
 */
class RefreshPetWithMedia
{
    public function handle(PetAttributeContext $context, Closure $next): mixed
    {
        $pet = $context->pet();

        $pet->refresh();
        $pet->loadMissing(['media', 'category.media', 'breed', 'user.media']);

        $context->setPet($pet);

        return $next($context);
    }
}
