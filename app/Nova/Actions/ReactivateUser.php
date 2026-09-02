<?php

namespace App\Nova\Actions;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;

/**
 * Restore a deactivated member account.
 *
 * The other half of DeactivateUser — read that class for what the flag
 * actually gates. Reactivation is a plain reversal: the next sign-in survives,
 * the public profile becomes visible again and messages are accepted again.
 * Nothing about the account's content changed while it was off, because
 * deactivation never touched it.
 *
 * Separate from DeactivateUser rather than one "toggle" action on purpose. A
 * toggle run over a mixed selection would deactivate half the rows and
 * reactivate the other half from a single confirmation, which is not something
 * a moderator can mean.
 *
 * ## The selection is one transaction, and a failure is a sentence
 *
 * Same shape and same trap as DeactivateUser, which spells the reasoning out:
 * the catch must `return`, and `$affected` must stay the value
 * `DB::transaction()` returns rather than a pre-initialised or by-reference
 * variable. Otherwise a run that threw and rolled back falls through to the
 * `=== 0` branch and reports "Nothing to do: every selected account was already
 * active." — a success sentence about a failure.
 */
class ReactivateUser extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name = 'Reactivate Account';

    /**
     * Perform the action on the given models.
     *
     * @param  Collection<int, User>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        try {
            $affected = DB::transaction(function () use ($models): int {
                return $models
                    ->reject(fn (User $user): bool => $user->isActive())
                    ->each(function (User $user): void {
                        $user->is_active = true;
                        $user->save();
                    })
                    ->count();
            });
        } catch (Throwable $exception) {
            report($exception);

            return ActionResponse::danger(
                'Nothing was changed. One of the selected accounts could not be reactivated, so the whole selection was rolled back. The failure has been logged.',
            );
        }

        if ($affected === 0) {
            return ActionResponse::message('Nothing to do: every selected account was already active.');
        }

        return ActionResponse::message(sprintf('%d account(s) reactivated.', $affected));
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
