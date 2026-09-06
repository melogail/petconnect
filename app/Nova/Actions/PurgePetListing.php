<?php

namespace App\Nova\Actions;

use App\Actions\Pets\PurgePet;
use App\Models\Pet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

/**
 * Permanently destroy listings, through the one supported code path.
 *
 * ## The dead end this opens
 *
 * `Actions\DeleteCategory` refuses a category that still has listings — counted
 * `withTrashed()`, because a soft-deleted pet keeps its `category_id` and that
 * is what the RESTRICT constraint sees — and tells the admin to "move or
 * permanently delete those listings first". PetPolicy::forceDelete is false and
 * there was no purge action, so the second half of that sentence named an
 * operation the back office did not have. A category whose last listing had
 * been retired could not be deleted by any route at all.
 *
 * ## Why not just turn forceDelete on
 *
 * Because Nova's built-in force delete is `$model->forceDelete()` and nothing
 * else, and a listing has four kinds of child that a hard delete gets wrong:
 * comments, likes and saves reach it through morph columns that carry no
 * foreign key and are not cascaded at all, and the reports against those
 * comments are stranded twice over. Media rows do cascade, but medialibrary
 * removes the *files* from an Eloquent `deleting` hook that a database cascade
 * never fires — so the bytes would stay on disk forever with nothing naming
 * them. `Actions\Pets\PurgePet` collects the thread first and clears each of
 * them explicitly inside one transaction; this class is a thin adapter onto it
 * and holds no deletion logic of its own.
 *
 * `forceDelete` therefore stays false in the policy, and `runDestructiveAction`
 * is what lets this action past it — the same arrangement DeleteUserAccount and
 * UserPolicy use for member accounts, for the same reason.
 *
 * The Action is resolved from the container rather than constructed here,
 * because it constructor-injects Illuminate\Pipeline\Pipeline;
 * Laravel\Nova\Makeable is `new static(...$arguments)` and cannot satisfy that.
 *
 * ## The selection is one transaction, and a failure is a sentence
 *
 * `Actions\Pets\PurgePet` wraps one listing, which made each listing's state
 * safe and the *selection* state arbitrary: a bare `$models->each(...)` left a
 * throw on the third of five as two listings permanently gone, three intact and
 * a 500 with a stack trace the admin could neither read nor act on — and this
 * is the one action in the back office with no undo at all, so guessing which
 * two went is not a recoverable position. An outer transaction makes the
 * selection all-or-nothing (the inner ones become savepoints) and the catch
 * turns the failure into a sentence. That is the shape
 * .ai/rules/nova-actions.md makes non-negotiable for every bulk action, and the
 * one DeleteCategory, DeleteCommentThread, DeleteReview and DeleteUserAccount
 * already have.
 *
 * ## The rollback restores rows, not bytes — which is why this message is longer
 *
 * The asymmetry is worth stating precisely, because it is invisible from the
 * call site. Everything `PurgePet` writes to the database is inside the
 * transaction and comes back on a rollback, savepoints included. The *files* do
 * not: medialibrary removes the bytes from an Eloquent `deleting` hook, and a
 * filesystem has no rollback and takes no part in the transaction. So a
 * selection that fails half way can leave a restored `pets` row, with its
 * `media` rows restored alongside it, pointing at photos that are already gone
 * from disk — a listing that exists again but renders a broken image.
 *
 * That is still strictly better than leaving half a selection permanently
 * destroyed, so the transaction stays. But the admin has to be told, which is
 * why this action's danger message carries the extra disk caveat the other four
 * do not: DeleteCategory touches nothing on disk and the report and account
 * status actions only write columns, so for those the rollback really does undo
 * everything the run did. DeleteUserAccount, which also deletes media, says the
 * same thing here for the same reason.
 */
class PurgePetListing extends DestructiveAction
{
    public function __construct(private readonly PurgePet $purgePet) {}

    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name = 'Permanently Delete Listing (with all content)';

    /**
     * Perform the action on the given models.
     *
     * @param  Collection<int, Pet>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        try {
            DB::transaction(function () use ($models): void {
                $models->each(function (Pet $pet): void {
                    $this->purgePet->handle($pet);
                });
            });
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger(
                'Nothing was deleted. One of the selected listings could not be removed, so the whole selection was rolled back. The failure has been logged; some uploaded photos may already have been removed from disk, which no transaction can undo.',
            );
        }

        return ActionResponse::message($models->count() === 1
            ? '1 listing permanently deleted, along with its comment thread, reactions, saves, reports and photos.'
            : sprintf(
                '%d listings permanently deleted, along with their comment threads, reactions, saves, reports and photos.',
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
