<?php

namespace App\Pipelines\Pets\Purge;

use Closure;

/**
 * Force delete the listing through Eloquent, so its files go too.
 *
 * `forceDelete()` rather than `delete()` for two reasons: `pets` soft deletes,
 * so a plain delete would only set `deleted_at` and the purge would remove the
 * thread and the reactions while leaving the listing itself behind; and
 * medialibrary deliberately preserves the files of a merely trashed model, so
 * only a force delete makes `InteractsWithMedia`'s `deleting` hook remove the
 * originals and every conversion from disk.
 *
 * Through the model, never through a query builder: a `whereKey()->forceDelete()`
 * is one query and fires no model events, which is the entire reason this step
 * exists rather than the caller issuing a DELETE.
 *
 * The pet arrives on the context already resolved, so this needs no
 * `withTrashed()` lookup — a listing the admin selected in Nova's trashed view
 * is the same instance.
 */
class PurgeListingRecord
{
    public function handle(PurgePetContext $context, Closure $next): mixed
    {
        $context->markPurged((bool) $context->pet->forceDelete());

        return $next($context);
    }
}
