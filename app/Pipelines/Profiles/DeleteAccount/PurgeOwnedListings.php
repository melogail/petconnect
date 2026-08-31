<?php

namespace App\Pipelines\Profiles\DeleteAccount;

use App\Models\Pet;
use Closure;

/**
 * Force delete the account's listings through Eloquent, so their files go too.
 *
 * ## Why not let the cascade take them
 *
 * `pets.user_id` is `cascadeOnDelete`, so the rows would disappear on their own
 * — but a database cascade fires no Eloquent events, and medialibrary removes a
 * model's files from `InteractsWithMedia`'s `deleting` hook. A cascaded listing
 * therefore leaves its `media` rows and every stored original and conversion on
 * the disk permanently, with no row left that names them: bytes nobody can find
 * and nobody can delete.
 *
 * Deleting each listing here, through the model, runs that hook.
 *
 * `withTrashed()` and `forceDelete()`, because `pets` soft deletes and the
 * cascade does not care: a hard delete of the user removes the row whatever
 * `deleted_at` says, so a retired listing's files would be stranded exactly
 * like a live one's. `forceDelete()` also skips medialibrary's own soft-delete
 * guard, which preserves files for a merely trashed model.
 *
 * ## Why a cursor and not a bulk delete
 *
 * A bulk `whereIn()->delete()` is one query and skips the hook, which is the
 * whole thing this step exists for. Listings are iterated one at a time so each
 * gets its `deleting` event. That is a query per listing plus the file work;
 * account deletion is rare, permanent and already inside a transaction, so
 * correctness wins over the round trips.
 *
 * `Actions\Pets\DeletePet` is not reused: it is the *retire* action, a soft
 * delete that deliberately keeps the row and its photos for moderation. This is
 * a purge, which is a different operation with the same noun.
 *
 * The listings' own polymorphic children — comments, likes, saves — are already
 * gone by the time this runs. Nothing here has to think about them.
 */
class PurgeOwnedListings
{
    public function handle(DeleteAccountContext $context, Closure $next): mixed
    {
        Pet::withTrashed()
            ->whereKey($context->petIds())
            ->cursor()
            ->each(fn (Pet $pet) => $pet->forceDelete());

        return $next($context);
    }
}
