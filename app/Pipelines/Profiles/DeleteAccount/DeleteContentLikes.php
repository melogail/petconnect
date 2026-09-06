<?php

namespace App\Pipelines\Profiles\DeleteAccount;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Pet;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Remove every like pointing at something this delete is about to destroy.
 *
 * Three targets, all reached through a morph column with no foreign key:
 *
 * - the account's **profile** — `User` implements Likeable, so a profile can be
 *   liked and those rows have nothing to cascade off;
 * - its **listings**, which the `pets.user_id` cascade takes without an event;
 * - its **comments** and every descendant of them, which
 *   `comments.parent_id` takes the same way.
 *
 * Left behind they are not merely untidy: `withCount('likes')` keeps counting
 * them and `isLikedBy()` keeps returning true, so a future row that inherits
 * the id inherits a like total and a "you already liked this" that belong to
 * something nobody can see. (Ids are *not* recycled on this schema —
 * `$table->id()` emits AUTOINCREMENT, see .ai/rules/migrations.md — but a
 * MySQL `TRUNCATE` does reset the counter, and the counts are wrong from the
 * moment the parent goes regardless.)
 *
 * Likes the account *gave* are not touched: `likes.user_id` cascades them.
 *
 * One DELETE for all three sets. `Like` has an observer, but it implements
 * `created` only, so a bulk delete skips nothing — the same reasoning
 * DeleteCommentThread\DeleteSubtreeLikes records.
 */
class DeleteContentLikes
{
    public function handle(DeleteAccountContext $context, Closure $next): mixed
    {
        Like::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('likeable_type', Relation::getMorphAlias(User::class))
                ->where('likeable_id', $context->user->getKey()))
            ->orWhere(fn (Builder $query): Builder => $query
                ->where('likeable_type', Relation::getMorphAlias(Pet::class))
                ->whereIn('likeable_id', $context->petIds()))
            ->orWhere(fn (Builder $query): Builder => $query
                ->where('likeable_type', Relation::getMorphAlias(Comment::class))
                ->whereIn('likeable_id', $context->commentIds()))
            ->delete();

        return $next($context);
    }
}
