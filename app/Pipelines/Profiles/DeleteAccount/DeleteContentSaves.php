<?php

namespace App\Pipelines\Profiles\DeleteAccount;

use App\Models\Pet;
use App\Models\Save;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Remove every bookmark pointing at one of the account's listings.
 *
 * `saves.saveable_id` is a morph column, so the `pets.user_id` cascade takes
 * the listing and leaves other users' bookmarks of it behind. Unlike a stranded
 * like, a stranded save is visible to the person who made it: their saved list
 * would show — or fail to render — an entry with no listing.
 *
 * Saves the account itself made are not touched: `saves.user_id` cascades them.
 *
 * `Pet` is currently the only saveable model. This is its own step rather than
 * an arm of DeleteContentLikes because they are different tables with different
 * unique indexes, and because a second saveable model belongs here rather than
 * inside a class named for likes.
 */
class DeleteContentSaves
{
    public function handle(DeleteAccountContext $context, Closure $next): mixed
    {
        Save::query()
            ->where('saveable_type', Relation::getMorphAlias(Pet::class))
            ->whereIn('saveable_id', $context->petIds())
            ->delete();

        return $next($context);
    }
}
