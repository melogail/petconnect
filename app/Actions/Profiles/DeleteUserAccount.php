<?php

namespace App\Actions\Profiles;

use App\Models\User;
use App\Pipelines\Profiles\DeleteAccount\CollectAccountContent;
use App\Pipelines\Profiles\DeleteAccount\DeleteAccountContext;
use App\Pipelines\Profiles\DeleteAccount\DeleteAccountNotifications;
use App\Pipelines\Profiles\DeleteAccount\DeleteAccountRecord;
use App\Pipelines\Profiles\DeleteAccount\DeleteContentComments;
use App\Pipelines\Profiles\DeleteAccount\DeleteContentLikes;
use App\Pipelines\Profiles\DeleteAccount\DeleteContentReports;
use App\Pipelines\Profiles\DeleteAccount\DeleteContentSaves;
use App\Pipelines\Profiles\DeleteAccount\DeleteReviewsAboutAccount;
use App\Pipelines\Profiles\DeleteAccount\PurgeOwnedListings;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;

/**
 * Permanently delete a user account and everything that would otherwise be
 * stranded by the database cascade.
 *
 * **This is the only supported way to delete a User.** A bare `$user->delete()`
 * leaves dangling likes, saves, reports, reviews, comments, notifications and
 * media files behind, silently — read DeleteAccountContext for the full list
 * and the measurement. That constraint is recorded in .ai/rules/actions.md.
 *
 * ## Order
 *
 * Collect first, while the rows still exist. Then the reactions (reports,
 * likes, saves), then the content they pointed at (comments, reviews), then the
 * listings — through Eloquent, so medialibrary removes their files — then the
 * account's notifications, and finally the user row itself. Nothing is deleted
 * before the thing that references it.
 *
 * ## The transaction is opened here, not in a step
 *
 * It has to span all nine steps. Any narrower boundary can commit a state where
 * a listing is gone and its comments are not, or where the reactions are
 * cleared and the account survives — worse than not deleting at all, because
 * the first is invisible and the second is irreversible in the other direction.
 *
 * One thing the transaction cannot undo: PurgeOwnedListings and
 * DeleteAccountRecord delete **files** through medialibrary, and a filesystem
 * has no rollback. A failure after the first file goes leaves the rows intact
 * and some photos missing. The alternative ordering — rows first, files
 * afterwards, outside the transaction — trades that for permanently orphaned
 * bytes on every failure instead of some, so this is the better of two
 * imperfect options and is stated rather than hidden.
 *
 * ## Not soft deletes, and not a queue
 *
 * `users` has no SoftDeletes and this phase does not add them: a soft-deleting
 * `users` table would need a global scope, and every one of the eight
 * `cascadeOnDelete` foreign keys pointing at it would keep hard-deleting on the
 * eventual force delete anyway, so it would move the problem rather than solve
 * it. Deletion is immediate rather than queued because the application has no
 * queue worker configured; when it does, this Action is what the job calls.
 *
 * The legacy destroy was `$user->delete(); auth()->logout();` with a
 * `// TODO::Send an email to verify the account deletion.` above it — no
 * confirmation, no session invalidation, no cleanup of any kind.
 */
class DeleteUserAccount
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function handle(User $user): bool
    {
        $context = new DeleteAccountContext($user);

        return DB::transaction(fn (): bool => $this->pipeline
            ->send($context)
            ->through([
                CollectAccountContent::class,
                DeleteContentReports::class,
                DeleteContentLikes::class,
                DeleteContentSaves::class,
                DeleteContentComments::class,
                DeleteReviewsAboutAccount::class,
                PurgeOwnedListings::class,
                DeleteAccountNotifications::class,
                DeleteAccountRecord::class,
            ])
            ->then(fn (DeleteAccountContext $completed): bool => $completed->deleted()));
    }
}
