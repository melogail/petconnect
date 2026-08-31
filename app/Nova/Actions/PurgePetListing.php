<?php

namespace App\Nova\Actions;

use App\Actions\Pets\PurgePet;
use App\Models\Pet;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

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
        $models->each(function (Pet $pet): void {
            $this->purgePet->handle($pet);
        });

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
