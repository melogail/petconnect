<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Pipelines\Pets\Purge\CollectListingContent;
use App\Pipelines\Pets\Purge\DeleteListingComments;
use App\Pipelines\Pets\Purge\DeleteListingLikes;
use App\Pipelines\Pets\Purge\DeleteListingReports;
use App\Pipelines\Pets\Purge\DeleteListingSaves;
use App\Pipelines\Pets\Purge\PurgeListingRecord;
use App\Pipelines\Pets\Purge\PurgePetContext;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;

/**
 * Permanently destroy one listing and everything that points at it.
 *
 * The transaction is opened here rather than in a step, because it has to span
 * all six: the comment subtree is read, its reports and likes go, the listing's
 * likes and saves go, the thread goes, and only then the listing itself. Any
 * boundary narrower than the whole flow can leave likes counting a listing
 * nobody can open or reports queued against comments that no longer exist —
 * the orphan class .ai/rules/actions.md is about.
 *
 * Read PurgePetContext for why the database cascade cannot be trusted with any
 * of this, and for why Profiles\DeleteAccount\PurgeOwnedListings is not lifted
 * to do it.
 *
 * **This is not `Actions\Pets\DeletePet`.** That one retires a listing: `pets`
 * soft deletes, the row and its photos and its thread stay for moderation, and
 * that remains what the member-facing delete and Nova's own delete button do.
 * This is the irreversible one, reachable only from
 * Nova\Actions\PurgePetListing behind a destructive confirmation, and
 * PetPolicy::forceDelete stays false so Nova's built-in force delete never
 * becomes a second, unguarded route to it.
 */
class PurgePet
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(Pet $pet): bool
    {
        $context = new PurgePetContext($pet);

        return DB::transaction(fn (): bool => $this->pipeline
            ->send($context)
            ->through([
                CollectListingContent::class,
                DeleteListingReports::class,
                DeleteListingLikes::class,
                DeleteListingSaves::class,
                DeleteListingComments::class,
                PurgeListingRecord::class,
            ])
            ->then(fn (PurgePetContext $completed): bool => $completed->purged()));
    }
}
