<?php

namespace App\Pipelines\Pets\Purge;

use App\Models\Comment;
use App\Models\Report;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Remove every report filed against a comment on this listing.
 *
 * The listing itself is not reportable — `App\Enums\Reportable` has `comment`
 * and `review` and nothing else — so the whole exposure here is the thread.
 * `reports.reportable_id` is a morph column with no foreign key, so deleting a
 * comment strands its report, and the `comments.parent_id` cascade strands the
 * reports of every reply it takes without an event.
 *
 * A stranded report sits in the moderation queue with `reportable` resolving to
 * null: an item nobody can act on or dismiss. Clearing it afterwards now needs
 * a second destructive action (Nova\Actions\PurgeOrphanedReports), which is
 * exactly the work this step exists to avoid creating.
 *
 * The alias comes from `Relation::getMorphAlias()`, never a class name: the
 * morph map is enforced, so `reportable_type` holds `comment`, and a class-name
 * comparison would delete nothing and report success.
 */
class DeleteListingReports
{
    public function handle(PurgePetContext $context, Closure $next): mixed
    {
        $commentIds = $context->commentIds();

        if ($commentIds !== []) {
            Report::query()
                ->where('reportable_type', Relation::getMorphAlias(Comment::class))
                ->whereIn('reportable_id', $commentIds)
                ->delete();
        }

        return $next($context);
    }
}
