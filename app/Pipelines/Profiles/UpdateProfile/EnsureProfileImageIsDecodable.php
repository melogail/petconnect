<?php

namespace App\Pipelines\Profiles\UpdateProfile;

use App\Exceptions\Profiles\ProfileImageNotDecodable;
use App\MediaLibrary\ImageDecodeVerifier;
use Closure;

/**
 * Refuse an avatar the conversion driver cannot read.
 *
 * The Form Request's `image` and `mimes:` rules both decide on the sniffed mime
 * type, so a file with a genuine JPEG header and padding behind it reaches this
 * flow intact. Without this step the failure surfaces inside `addMedia()` in
 * UploadProfileImage — `User` registers `thumb` and `display` as `nonQueued()`,
 * so `FileManipulator::performConversions()` runs in-process there — and
 * escapes the pipeline as a 500 with the original already copied onto the disk.
 *
 * **It runs first, ahead of UploadProfileImage.** That ordering is the guarantee
 * this step exists to give, and the mechanism is the abort, not a guard: the
 * throw leaves the pipeline here, so UploadProfileImage never adds anything and
 * ClearPreviousProfileImage is never entered at all. No guard is evaluated and
 * no delete is registered. The account's existing avatar is untouched by
 * construction rather than by the transaction unwinding around it.
 *
 * (ClearPreviousProfileImage's `uploadedMedia() === null` guard is for a
 * different case — a save that carries no new image, where the run reaches that
 * step and it must decline. It plays no part on this path, because this path
 * does not reach it.)
 *
 * Ordering rather than a guard because a 422 that has already deleted the old
 * avatar is still data loss, and because a conversion moved to `queued()` or
 * `deferred()` would be dispatched *after* the commit that already ran the
 * clear — a window `DB::afterCommit()` cannot close and this step does. That is
 * a hazard for a future configuration rather than today's behaviour; see
 * Actions\Profiles\UpdateProfile for the two conditions it needs.
 *
 * The passable is UpdateProfileContext and not a shared abstract: per
 * .ai/rules/pipelines.md a step in a flow directory hints that flow's context.
 *
 * The step reads no configuration — the driver the decode is attempted with
 * belongs to the verifier it is handed, which is the same
 * App\MediaLibrary\ImageDecodeVerifier the listing flow's
 * Pipelines\Pets\Shared\EnsurePhotosAreDecodable uses. One decode check in the
 * application, not one per vertical.
 *
 * @throws ProfileImageNotDecodable
 */
class EnsureProfileImageIsDecodable
{
    public function __construct(private readonly ImageDecodeVerifier $verifier) {}

    public function handle(UpdateProfileContext $context, Closure $next): mixed
    {
        if ($context->hasImage() && ! $this->verifier->canDecode($context->image)) {
            throw ProfileImageNotDecodable::forAvatar();
        }

        return $next($context);
    }
}
