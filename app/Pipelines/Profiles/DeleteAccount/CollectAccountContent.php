<?php

namespace App\Pipelines\Profiles\DeleteAccount;

use App\Models\Comment;
use App\Models\Pet;
use App\Models\Review;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Gather every id the delete is about to remove, while the rows still exist.
 *
 * Runs first. Every step after it deletes polymorphic children *by id*, and by
 * the time the FK cascade has run those ids are gone with no trace of what they
 * were — which is exactly how the orphans this flow exists to prevent are
 * created.
 *
 * ## Three sets, and why each is wider than it looks
 *
 * **Listings** include trashed ones (`withTrashed()`). `pets` soft deletes, but
 * `pets.user_id` is `cascadeOnDelete`, so a hard delete of the user removes the
 * row whatever `deleted_at` says — a retired listing's photos and comments
 * would be stranded just as thoroughly as a live one's.
 *
 * **Comments** are the union of the ones the account wrote and the ones anyone
 * wrote on its listings, plus every descendant of both. The account's own
 * comments go by `comments.user_id`; the comments on its listings go because
 * the listing goes and `commentable_id` is a morph column with nothing to
 * cascade — they have to be deleted here (DeleteContentComments) or they
 * survive pointing at a listing that no longer exists. Descendants are walked
 * level by level, one `whereIn('parent_id')` per level, the same way
 * DeleteCommentThread\CollectCommentSubtree does it: publishing caps threads at
 * two levels, but Nova and imports do not go through that cap, and a missed
 * level is a missed set of likes and reports.
 *
 * **Reviews** are the union of the ones the account wrote — which cascade off
 * `reviews.user_id`, taking their reports with them if nobody deletes those —
 * and the ones written **about** the account, which cascade off nothing at all
 * because `reviewable_id` is a morph column. The second set is the one
 * Actions\Reviews\DeleteReview cannot reach and the one that leaves a
 * moderation queue full of null targets.
 *
 * The morph filters go through `Relation::getMorphAlias()`, never a class name:
 * the morph map is enforced, so `commentable_type` holds `pet` and a
 * `where('commentable_type', Pet::class)` would match zero rows and say nothing
 * about it. See .ai/rules/app.md.
 */
class CollectAccountContent
{
    public function handle(DeleteAccountContext $context, Closure $next): mixed
    {
        $userId = $context->user->getKey();

        $petIds = Pet::withTrashed()
            ->where('user_id', $userId)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $context->setCollectedContent(
            petIds: array_values($petIds),
            commentIds: $this->commentIds($userId, $petIds),
            reviewIds: $this->reviewIds($userId),
        );

        return $next($context);
    }

    /**
     * Comments the account wrote plus comments on its listings, then every
     * descendant of either.
     *
     * @param  list<int>  $petIds
     * @return list<int>
     */
    protected function commentIds(int $userId, array $petIds): array
    {
        $roots = Comment::query()
            ->where('user_id', $userId)
            ->orWhere(fn (Builder $query): Builder => $query
                ->where('commentable_type', Relation::getMorphAlias(Pet::class))
                ->whereIn('commentable_id', $petIds))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $collected = $roots;
        $frontier = $roots;

        while ($frontier !== []) {
            $frontier = Comment::query()
                ->whereIn('parent_id', $frontier)
                ->whereNotIn('id', $collected)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            $collected = [...$collected, ...$frontier];
        }

        return array_values(array_unique($collected));
    }

    /**
     * Reviews the account wrote and reviews written about it.
     *
     * @return list<int>
     */
    protected function reviewIds(int $userId): array
    {
        return array_values(array_unique(Review::query()
            ->where('user_id', $userId)
            ->orWhere(fn (Builder $query): Builder => $query
                ->where('reviewable_type', Relation::getMorphAlias(User::class))
                ->where('reviewable_id', $userId))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all()));
    }
}
