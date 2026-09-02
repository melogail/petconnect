<?php

namespace App\Actions\Profiles;

use App\Models\User;
use App\Pipelines\Profiles\UpdateProfile\ApplyLocalePreference;
use App\Pipelines\Profiles\UpdateProfile\ClearPreviousProfileImage;
use App\Pipelines\Profiles\UpdateProfile\EnsureProfileImageIsDecodable;
use App\Pipelines\Profiles\UpdateProfile\PersistProfileAttributes;
use App\Pipelines\Profiles\UpdateProfile\UpdateProfileContext;
use App\Pipelines\Profiles\UpdateProfile\UploadProfileImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;

/**
 * Apply a user's edits to their own profile.
 *
 * A sequence — check the avatar can be read, upload it, clear the one it
 * replaced, write the attributes, switch the language — so it runs as a
 * pipeline over a typed context. The legacy UpdateUserProfileAction did all of
 * it in one method, and got the order of two of them wrong.
 *
 * ## Order is load bearing, and one pair of steps is the whole point
 *
 * UploadProfileImage comes **before** ClearPreviousProfileImage, which is the
 * correction this flow exists for: the legacy ProfileImageService cleared the
 * collection first, so a failed upload left the account with no avatar and
 * nothing to restore. Read UpdateProfileContext for the verified legacy code
 * and the full reasoning.
 *
 * EnsureProfileImageIsDecodable comes before **both** of them, for the one
 * upload failure the pair above does not cover cleanly. `image` and `mimes:`
 * decide on the sniffed header, so a file with a real JPEG marker and padding
 * behind it validates; the decode only fails when a conversion asks the driver
 * for the pixels. That throw lands inside `addMedia()` in UploadProfileImage —
 * a 500, and the original already copied onto the disk by the time it happens,
 * because `Filesystem::add()` runs `copyToMediaLibrary()` and only then
 * `FileManipulator::createDerivedFiles()`, in the same call.
 *
 * Two separate facts hold that up, and the evidence for each is its own — do
 * not read either citation as covering the other:
 *
 * - **A conversion that throws escapes `addMedia()` in-process, with the
 *   original already written.** That is what
 *   tests/Feature/MediaLibrary/UndecodableImageUploadTest's control case pins,
 *   and all it pins: it uploads to a `Pet`, whose `display` is `queued()` and
 *   runs inline only because `phpunit.xml` sets `QUEUE_CONNECTION=sync`.
 * - **An avatar takes that in-process path with no queue setting involved.**
 *   `User::registerMediaConversions()` (User.php:240-249) marks both `thumb`
 *   and `display` `->nonQueued()`, so `performConversions()` runs synchronously
 *   there whatever the queue configuration says. The `User` path's own outcome
 *   is pinned by tests/Feature/Settings/UndecodableAvatarUploadTest.
 *
 * Checking the file up front turns that 500 into a 422 on `image` and leaves
 * nothing written at all.
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
 * - **An orphan file with no media row.** Medialibrary copies the new avatar's
 *   bytes onto the disk inside `addMedia()` and only the row is transactional,
 *   so a rollback strands them. Wasted storage, unreferenced and unreachable — the
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
 * ## What actually protects the previous avatar, stated once
 *
 * Two mechanisms, and they cover different failures — do not read either as
 * doing the other's job:
 *
 * - **Ordering** covers a bad *new* image. EnsureProfileImageIsDecodable and
 *   UploadProfileImage both run before ClearPreviousProfileImage, and a throw
 *   from either aborts the pipeline where it happens: the clear step is never
 *   entered, no guard is evaluated, and no delete is registered. (Its
 *   `uploadedMedia() === null` guard is for the save that carries no new image
 *   at all, where the run does reach the step and it must decline.) The old row
 *   and its file are untouched — not restored, never acted on.
 * - **`DB::afterCommit()`** covers a good new image and a failure *later in the
 *   run* (PersistProfileAttributes, ApplyLocalePreference, a unique race). The
 *   callback is discarded with the rollback, so the old file survives there
 *   too.
 *
 * The gap ordering closes and afterCommit cannot is a conversion that throws
 * *after* the commit. Nothing produces it today, and it takes two things
 * together to produce it at all:
 *
 * 1. **A User conversion moved to `queued()` or `deferred()`.** The key that
 *    actually pushes the failure past the commit is
 *    `config/media-library.php:97`, `queue_conversions_after_database_commit`,
 *    which is `true`: `FileManipulator::dispatchQueuedConversions()` then sends
 *    the job with `dispatch($job)->afterCommit()`, so it is handed over from
 *    the commit callbacks — by which point ClearPreviousProfileImage's deferred
 *    delete has already fired. Flip that key to `false` and this analysis
 *    changes, so name it rather than reasoning from `queued()` alone.
 * 2. **A deployed queue worker.** With none running (.ai/rules/models.md) a
 *    queued conversion is never generated and therefore never throws — it
 *    silently serves the original upload instead, which is a different defect
 *    and not this one. Only with a worker *and* queued conversions would the
 *    replaced avatar be gone for good.
 *
 * The up-front check is right either way, which is worth stating because a
 * reader who notices there is no worker might otherwise conclude it is
 * unnecessary. It runs before anything is written, so the gap stays shut
 * whatever a future conversion's queue setting and deployment are. What it buys
 * *today* is narrower and should not be overstated: with both conversions
 * `nonQueued()` the pre-fix failure was a 500 plus an orphan file on the disk —
 * the file, not the row. `FileAdder::processMediaItem()` saves the row and then
 * calls `Filesystem::add()`, which copies the bytes before running the
 * conversion that throws, so the transaction takes the row back and nothing
 * takes the bytes back. The previous avatar was never at risk on that path.
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
                EnsureProfileImageIsDecodable::class,
                UploadProfileImage::class,
                ClearPreviousProfileImage::class,
                PersistProfileAttributes::class,
                ApplyLocalePreference::class,
            ])
            ->then(fn (UpdateProfileContext $completed): User => $completed->user));
    }
}
