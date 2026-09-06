<?php

namespace App\Pipelines\Pets\Purge;

use App\Models\Pet;
use App\Models\Save;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Remove every save of this listing.
 *
 * `saves.saveable_id` is a morph column with no foreign key, so a saved listing
 * that is purged leaves the row in place and the member's saved list keeps a
 * bookmark to nothing.
 *
 * Comments are not saveable, so unlike likes this is one target rather than
 * two. A new saveable type is a new arm here, not a new step.
 */
class DeleteListingSaves
{
    public function handle(PurgePetContext $context, Closure $next): mixed
    {
        Save::query()
            ->where('saveable_type', Relation::getMorphAlias(Pet::class))
            ->where('saveable_id', $context->pet->getKey())
            ->delete();

        return $next($context);
    }
}
