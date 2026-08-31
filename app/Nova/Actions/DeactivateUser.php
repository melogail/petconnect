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

/**
 * Deactivate member accounts.
 *
 * Before this existed, `users.is_active` had no writer anywhere in the
 * application: it is outside User's #[Fillable] and outside every Form
 * Request by design, and there is no self-service deactivation. This action
 * and ReactivateUser are the intended pair.
 *
 * What deactivation means is settled in .ai/rules/app.md and asked through the
 * single predicate `User::isActive()`:
 *
 * 1. Http\Middleware\EnsureAccountIsActive ends the session on the account's
 *    very next request, whatever established it — password, passkey or a
 *    surviving cookie.
 * 2. A fresh sign-in succeeds and is then ended by the same middleware, so
 *    every way in is covered by one check.
 * 3. UserPolicy::view returns false, so the public profile 403s for everyone.
 * 4. User::acceptsMessagesFrom() refuses delivery.
 *
 * What it deliberately does **not** do is retire the account's listings,
 * comments or reviews. Those stay published; taking content down is a separate
 * moderation decision with its own action and its own audit trail.
 *
 * The column is assigned as a property rather than mass assigned, for the same
 * reason ChangeReportStatus does: it is outside #[Fillable] on purpose and
 * `update(['is_active' => ...])` would either be discarded or require widening
 * the model's write surface for everything else.
 */
class DeactivateUser extends Action
{
    /**
     * The displayable name of the action.
     *
     * @var \Stringable|string
     */
    public $name = 'Deactivate Account';

    /**
     * Perform the action on the given models.
     *
     * @param  Collection<int, User>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $affected = DB::transaction(function () use ($models): int {
            return $models
                ->filter(fn (User $user): bool => $user->isActive())
                ->each(function (User $user): void {
                    $user->is_active = false;
                    $user->save();
                })
                ->count();
        });

        if ($affected === 0) {
            return ActionResponse::message('Nothing to do: every selected account was already deactivated.');
        }

        return ActionResponse::message(sprintf('%d account(s) deactivated.', $affected));
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
