<?php

namespace App\Pipelines\Profiles\UpdateProfile;

use Closure;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Remove the avatar the new one replaced — after the transaction commits.
 *
 * Runs only when UploadProfileImage actually attached something. That guard is
 * the whole point of splitting this from the upload: the legacy service cleared
 * the collection *before* uploading, so a failed upload destroyed the existing
 * avatar and left the account with none. Here a failure never reaches this
 * step. See UpdateProfileContext.
 *
 * Deletion is by the ids recorded before the upload, never by
 * `clearMediaCollection('users')` — that would delete the file this run just
 * added.
 *
 * ## Why the delete is deferred to `DB::afterCommit()`
 *
 * Medialibrary removes the bytes in `MediaObserver::deleted()`, which calls
 * `Filesystem::removeAllFiles()` **the moment `$media->delete()` returns from
 * the database** — not when the surrounding transaction commits. Deleting a
 * file is not transactional and never will be.
 *
 * So running the delete inline, inside the Action's transaction and ahead of
 * PersistProfileAttributes, opened a window: anything that threw after it —
 * a DB-level unique race on `username` or `email`, a
 * `preventSilentlyDiscardingAttributes` violation — rolled the media row back
 * into existence while its file stayed deleted. The account was left with a
 * live media row pointing at nothing: a permanently broken avatar, and the one
 * direction of this failure a user actually sees. Reordering the steps only
 * narrows that window; it does not close it, because every step after this one
 * can still throw.
 *
 * Registering the delete with `DB::afterCommit()` closes it. The callback is
 * discarded if the transaction rolls back, so a failed save leaves the previous
 * avatar and its file exactly as they were, and it fires only once the user row
 * is durable — including when a caller has wrapped this Action in a transaction
 * of its own, which is the case a post-`DB::transaction()` delete in the Action
 * would still get wrong. The residual failure is an orphan file with no media
 * row, which is unreferenced and unreachable rather than visibly broken.
 *
 * Each row is deleted through the Media model rather than with a bulk
 * `whereIn()->delete()`, because of that same `deleted` hook. A bulk delete
 * would drop the rows and leave the bytes on the disk forever, which is the
 * same class of silent orphan a morph column produces.
 *
 * The `users` collection is not `singleFile`, deliberately (see User::
 * registerMediaCollections), so nothing removes the old avatar automatically
 * and this step is what keeps one row per account in practice.
 */
class ClearPreviousProfileImage
{
    public function handle(UpdateProfileContext $context, Closure $next): mixed
    {
        if ($context->uploadedMedia() === null || $context->previousMediaIds() === []) {
            return $next($context);
        }

        $previousMediaIds = $context->previousMediaIds();

        DB::afterCommit(function () use ($previousMediaIds): void {
            Media::query()
                ->whereKey($previousMediaIds)
                ->get()
                ->each(fn (Media $media) => $media->delete());
        });

        return $next($context);
    }
}
