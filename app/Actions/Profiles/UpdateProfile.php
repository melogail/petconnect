<?php

namespace App\Actions\Profiles;

use App\Models\User;
use App\Pipelines\Profiles\UpdateProfile\ApplyLocalePreference;
use App\Pipelines\Profiles\UpdateProfile\ClearPreviousProfileImage;
use App\Pipelines\Profiles\UpdateProfile\PersistProfileAttributes;
use App\Pipelines\Profiles\UpdateProfile\UpdateProfileContext;
use App\Pipelines\Profiles\UpdateProfile\UploadProfileImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;

/**
 * Apply a user's edits to their own profile.
 *
 * A sequence — upload the avatar, clear the one it replaced, write the
 * attributes, switch the language — so it runs as a pipeline over a typed
 * context. The legacy UpdateUserProfileAction did all of it in one method, and
 * got the order of two of them wrong.
 *
 * ## Order is load bearing, and one pair of steps is the whole point
 *
 * UploadProfileImage comes **before** ClearPreviousProfileImage, which is the
 * correction this flow exists for: the legacy ProfileImageService cleared the
 * collection first, so a failed upload left the account with no avatar and
 * nothing to restore. Read UpdateProfileContext for the verified legacy code
 * and the full reasoning.
 *
 * ## It no longer changes the password, and that is deliberate
 *
 * The run used to open with VerifyCurrentPassword and HashNewPassword, because
 * the profile form accepted a password change that Fortify's
 * `user-password.update` also accepted from `settings/Security`. Both steps and
 * App\Exceptions\Profiles\IncorrectCurrentPassword are deleted rather than left
 * unreachable: a step nothing can reach is a claim about behaviour that is no
 * longer true. See App\Concerns\ProfileValidationRules for the decision, and
 * note it as a divergence from the legacy app rather than a port of it.
 *
 * ApplyLocalePreference is last because it is about the *rest of the request* —
 * the locale the redirect renders in, the session, the cookie — and there is no
 * point switching language for a save that then fails.
 *
 * ## The transaction, and what it cannot cover
 *
 * The run is wrapped in one transaction, opened here rather than in a step,
 * because the media rows and the user row have to land together: a committed
 * avatar beside a rolled-back name change would leave the account showing a
 * photo it never agreed to.
 *
 * What a transaction cannot roll back is the **file** — deleting or writing one
 * is not transactional and never will be. That cuts in two directions, and only
 * one of them is acceptable:
 *
 * - **An orphan file with no media row.** Medialibrary has already copied the
 *   new avatar's bytes onto the disk by the time its row is written, so a
 *   rollback strands them. Wasted storage, unreferenced and unreachable — the
 *   right direction for the failure to fall, and the price of the transaction.
 *   The alternative (no transaction) trades it for a user whose avatar changed
 *   while their edits did not, which is visible and wrong.
 * - **A live media row with no file, which is not acceptable and is why
 *   ClearPreviousProfileImage defers.** `MediaObserver::deleted()` removes the
 *   bytes the instant `$media->delete()` returns, not on commit. Deleting the
 *   replaced avatar inline — inside this transaction, ahead of
 *   PersistProfileAttributes — meant any later throw (a DB-level unique race on
 *   `username` or `email`, a `preventSilentlyDiscardingAttributes` violation)
 *   restored the row over a file that was already gone: a permanently broken
 *   avatar on a save the user was told had failed. The step now registers that
 *   delete with `DB::afterCommit()`, so a rollback discards it and the previous
 *   avatar survives intact. Reordering the steps would only have narrowed the
 *   window, since every step after the clear can still throw.
 *
 * ## Authorization is not here
 *
 * .ai/rules/controllers.md puts it in the controller, and
 * Settings\ProfileController::update calls `$this->authorize('update', ...)`
 * against UserPolicy. This Action takes the user it is told to edit and asks no
 * questions about who is asking — which is also what lets a console command or
 * a future admin tool drive it.
 */
class UpdateProfile
{
    public function __construct(private readonly Pipeline $pipeline) {}

    /**
     * @param  array<string, mixed>  $attributes  Only the keys the request sent.
     */
    public function handle(
        User $user,
        array $attributes,
        ?UploadedFile $image = null,
    ): User {
        $context = new UpdateProfileContext(
            user: $user,
            attributes: $attributes,
            image: $image,
        );

        return DB::transaction(fn (): User => $this->pipeline
            ->send($context)
            ->through([
                UploadProfileImage::class,
                ClearPreviousProfileImage::class,
                PersistProfileAttributes::class,
                ApplyLocalePreference::class,
            ])
            ->then(fn (UpdateProfileContext $completed): User => $completed->user));
    }
}
