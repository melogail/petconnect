<?php

namespace App\Pipelines\Profiles\DeleteAccount;

use App\Models\Comment;
use App\Models\Report;
use App\Models\Review;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Remove every report filed against content this delete is about to destroy.
 *
 * The sharpest of the orphan cases, and the one that was measured: A reviews B,
 * C reports that review, A's account is deleted — `reviews.user_id` cascades
 * the review away and C's report survives, sitting in the moderation queue with
 * `reportable` resolving to null. A moderator can neither act on it nor dismiss
 * it, and one more arrives with every account closed.
 *
 * Both reportable types are handled here because `reports` is one table with
 * one shape; splitting into per-type steps would be two classes doing the
 * identical DELETE with a different alias. A *new* polymorphic child of User
 * gets its own step; a new reportable type is a new arm here.
 *
 * Reports the account *filed* are not touched: `reports.user_id` cascades them,
 * and their targets still exist.
 *
 * The morph aliases come from `Relation::getMorphAlias()`, never a class name —
 * the morph map is enforced, so `reportable_type` holds `review` and `comment`,
 * and a class-name comparison would delete nothing and report success.
 */
class DeleteContentReports
{
    public function handle(DeleteAccountContext $context, Closure $next): mixed
    {
        Report::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('reportable_type', Relation::getMorphAlias(Review::class))
                ->whereIn('reportable_id', $context->reviewIds()))
            ->orWhere(fn (Builder $query): Builder => $query
                ->where('reportable_type', Relation::getMorphAlias(Comment::class))
                ->whereIn('reportable_id', $context->commentIds()))
            ->delete();

        return $next($context);
    }
}
