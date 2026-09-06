<?php

namespace App\Nova\Actions;

use App\Models\Category;
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
 * Delete a category, but only once nothing still points at it.
 *
 * ## The trap this exists for
 *
 * `pets.category_id` is `restrictOnDelete` and `pets` uses SoftDeletes. A
 * soft-deleted pet keeps its row and therefore keeps its `category_id`, so the
 * foreign key still holds even though the listing is invisible everywhere in
 * the application. Deleting the category then fails at the driver — on SQLite
 * an `Illuminate\Database\QueryException` reading
 * `SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint
 * failed` — which Nova surfaces as a 500 and a red toast with no explanation.
 * An admin looking at "0 pets" in the listing has no way to work out why.
 * Recorded in .ai/rules/migrations.md.
 *
 * ## The guard
 *
 * The count is taken `withTrashed()`, because that is the number the foreign
 * key actually sees, and it is taken *before* any delete is attempted. A
 * category that still has listings is reported by name with its count and the
 * whole run stops without writing anything; nothing partially succeeds.
 *
 * Nova's built-in delete button is switched off for this resource
 * (App\Nova\Policies\CategoryPolicy::delete returns false), so this check
 * cannot be walked around — which is the point. `runDestructiveAction` in that
 * policy is what lets this action past the same refusal.
 *
 * `breeds.category_id` is `cascadeOnDelete`, so a category's breeds go with
 * it; that is stated in the confirmation text rather than blocked, because a
 * breed has no meaning without its category.
 *
 * ## The selection is one transaction, and a failure is a sentence
 *
 * The guard above is not the only way this can fail: a category can acquire a
 * listing between the count and the delete, and `breeds.category_id` cascades
 * rows the guard never counted. Without the catch, a throw on the third of five
 * left the admin with a 500 and a stack trace instead of a sentence — the exact
 * failure .ai/rules/nova-policies.md records against DeleteUserAccount, and the
 * shape .ai/rules/nova-actions.md makes non-negotiable for every bulk action:
 * DB::transaction around the whole selection, `catch (Throwable)` returning
 * ActionResponse::danger() so the admin is told nothing happened rather than
 * being left guessing which half did. Same shape as DeleteCommentThread,
 * DeleteReview and DeleteUserAccount.
 *
 * Nothing here touches the filesystem, so unlike DeleteUserAccount the rollback
 * really does undo everything the run did.
 *
 * ## `e()` on the category name is correct, and it was worth checking
 *
 * The refusal below runs the admin-typed name through `e()`, which is only
 * right if the toast renders HTML — otherwise a category called `Cats & Dogs`
 * would read `Cats &amp; Dogs`. It does. `ActionResponse::danger()` becomes
 * `Nova.error(message)`, which is `this.$toasted.show(message, {type:'error'})`,
 * and toastedjs assigns a string message with `toast.innerHTML = message`
 * (verified in the shipped bundle: nova/public/vendor.js). So the entity
 * decodes back to `&` on screen, and without `e()` a name containing markup
 * would be injected into the back office. Keep it.
 */
class DeleteCategory extends DestructiveAction
{
    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name = 'Delete Category';

    /**
     * Perform the action on the given models.
     *
     * @param  Collection<int, Category>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $blocked = $this->blockedBy($models);

        if ($blocked !== []) {
            return ActionResponse::danger($this->refusal($blocked));
        }

        try {
            DB::transaction(function () use ($models): void {
                $models->each(function (Category $category): void {
                    $category->delete();
                });
            });
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger(
                'Nothing was deleted. One of the selected categories could not be removed, so the whole selection was rolled back. The failure has been logged.',
            );
        }

        return ActionResponse::message($models->count() === 1
            ? '1 category deleted, along with its breeds.'
            : sprintf('%d categories deleted, along with their breeds.', $models->count()));
    }

    /**
     * The selected categories that still have listings, keyed by name.
     *
     * Counted `withTrashed()`: a soft-deleted pet still holds its
     * `category_id`, so it still satisfies the RESTRICT constraint even though
     * the listing is gone from every application query.
     *
     * One grouped aggregate for the whole selection, not `$category->pets()
     * ->withTrashed()->count()` per row: Nova hands `handle()` up to the page
     * size in models, and the per-row version issued that many `select count(*)`
     * round trips before the action had decided anything. A category with no
     * listings is simply absent from the result and reads as 0.
     *
     * @param  Collection<int, Category>  $models
     * @return array<string, int>
     */
    protected function blockedBy(Collection $models): array
    {
        $counts = Pet::withTrashed()
            ->whereIn('category_id', $models->map(fn (Category $category): int => (int) $category->getKey())->all())
            ->groupBy('category_id')
            ->selectRaw('category_id, count(*) as aggregate')
            ->pluck('aggregate', 'category_id');

        return $models
            ->mapWithKeys(fn (Category $category): array => [
                $category->name => (int) $counts->get($category->getKey(), 0),
            ])
            ->filter(fn (int $count): bool => $count > 0)
            ->all();
    }

    /**
     * The message an admin reads instead of a driver exception.
     *
     * @param  array<string, int>  $blocked
     */
    protected function refusal(array $blocked): string
    {
        $detail = collect($blocked)
            ->map(fn (int $count, string $name): string => sprintf(
                '%s (%d listing%s)',
                e($name),
                $count,
                $count === 1 ? '' : 's',
            ))
            ->implode(', ');

        return sprintf(
            'Nothing was deleted. %s still %s listings attached — including soft-deleted ones, which keep their category and are what the database constraint sees. Move or permanently delete those listings first.',
            $detail,
            count($blocked) === 1 ? 'has' : 'have',
        );
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
