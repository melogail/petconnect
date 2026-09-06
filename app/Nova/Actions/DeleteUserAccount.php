<?php

namespace App\Nova\Actions;

use App\Actions\Profiles\DeleteUserAccount as DeleteUserAccountAction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

/**
 * Permanently delete member accounts, through the one supported code path.
 *
 * **Never `$user->delete()`.** `users` has no soft deletes and eight foreign
 * keys cascade off it, and a database cascade fires no Eloquent events: a bare
 * delete strands roughly 227 rows across seven tables — likes, saves, reports,
 * reviews, comments, notifications and media — with the media *files* left on
 * disk forever. Measured, and recorded in .ai/rules/actions.md.
 * App\Actions\Profiles\DeleteUserAccount collects the affected ids before the
 * cascade can run and clears the polymorphic rows explicitly inside one
 * transaction; this action is a thin adapter onto it and holds no deletion
 * logic of its own.
 *
 * The Action is resolved from the container rather than constructed here,
 * because it constructor-injects Illuminate\Pipeline\Pipeline.
 *
 * Nova's own delete button is disabled for this resource for the same reason —
 * App\Nova\Policies\UserPolicy::delete returns false, so the only route to a
 * deleted account is this class. `runDestructiveAction` is what lets this
 * action past the `delete` refusal; see that policy.
 *
 * ## The selection is one transaction, and a failure says so
 *
 * The Action already wraps each account in its own transaction, which made the
 * per-account state safe and the *selection* state arbitrary: a throw on the
 * third of five left two accounts deleted, three intact, and a 500 with a stack
 * trace the admin could not read or act on. Nova offers no way to find out
 * which two, and re-running the action on the same selection would be a second
 * guess.
 *
 * An outer transaction makes the selection all-or-nothing (the inner ones
 * become savepoints), and the catch turns the failure into a sentence — the
 * same shape DeleteCategory has, for the same reason.
 *
 * The one thing neither can undo is files: PurgeOwnedListings and
 * DeleteAccountRecord remove media through medialibrary, and a filesystem has
 * no rollback. That trade-off is stated in the Action's own docblock and is
 * unchanged here; rolling the rows back is strictly better than leaving half a
 * selection deleted, which is what happened before.
 */
class DeleteUserAccount extends DestructiveAction
{
    public function __construct(private readonly DeleteUserAccountAction $deleteUserAccount) {}

    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name = 'Delete Account (with all content)';

    /**
     * Perform the action on the given models.
     *
     * @param  Collection<int, User>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        try {
            DB::transaction(function () use ($models): void {
                $models->each(function (User $user): void {
                    $this->deleteUserAccount->handle($user);
                });
            });
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger(
                'Nothing was deleted. One of the selected accounts could not be removed, so the whole selection was rolled back. The failure has been logged; some uploaded files may already have been removed from disk, which no transaction can undo.',
            );
        }

        return ActionResponse::message(sprintf(
            '%d account(s) deleted, along with every listing, comment, review, reaction, report, notification and uploaded file belonging to them.',
            $models->count(),
        ));
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
