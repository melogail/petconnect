<?php

namespace App\Pipelines\Profiles\UpdateProfile;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Passable for updating a user's own profile.
 *
 * ## The ordering this flow exists to fix
 *
 * The legacy ProfileImageService::update did, in this order:
 *
 *     $user->clearMediaCollection('users');          // old avatar destroyed
 *     $user->addMediaFromRequest('profile_image')    // new one uploaded
 *         ->usingFileName($filename)
 *         ->toMediaCollection('users');
 *
 * Verified in petconnect-old/app/Services/ProfileImageService.php. The clear
 * comes first, so any failure in the upload — an unreadable temp file, a full
 * disk, a conversion that throws, a rejected S3 write — left the account with
 * **no** avatar and nothing to restore: the previous file and its media row
 * were already gone, and there was no transaction, because deleting files is
 * not transactional in the first place.
 *
 * Here the order is reversed and the two halves are separate steps.
 * UploadProfileImage records the ids of the media rows that existed *before* it
 * adds anything, then adds. ClearPreviousProfileImage deletes exactly those
 * recorded ids, and only when an upload actually succeeded. A failed upload
 * throws out of the pipeline before the clear step is ever reached, so the
 * existing avatar is untouched and the user still has the photo they had.
 *
 * Deleting by *recorded ids* rather than by clearing the collection is the
 * other half of the fix: `clearMediaCollection()` after an upload would delete
 * the new file along with the old ones.
 *
 * The recorded ids outlive the pipeline run by design: ClearPreviousProfileImage
 * hands them to `DB::afterCommit()` rather than deleting inline, because
 * medialibrary removes a file the instant its row is deleted and a later step
 * can still roll the row back over the missing bytes. See that step and
 * Actions\Profiles\UpdateProfile for the window that closes.
 *
 * ## What is conditional, and how a step knows
 *
 * Four of the five steps only apply sometimes, and each decides for itself from
 * a question it asks this context — `hasImage()` for both
 * EnsureProfileImageIsDecodable and UploadProfileImage, `uploadedMedia()` (with
 * `previousMediaIds()`) for ClearPreviousProfileImage, `locale()` for
 * ApplyLocalePreference. Only PersistProfileAttributes runs unconditionally. No
 * step reads config and no step knows which step runs next, per
 * .ai/rules/pipelines.md; the Action resolves every tunable before the run and
 * passes it in.
 *
 * ## No password fields, deliberately
 *
 * This context carried `currentPassword`, `newPassword`, `wantsPasswordChange()`
 * and a `hashedPassword` slot for the two steps that have been deleted. A
 * password change is Fortify's `user-password.update` and nothing else now —
 * see App\Concerns\ProfileValidationRules for the decision. Nothing plain-text
 * and credential-shaped travels on this passable any more, which also retires
 * the care the old HashNewPassword docblock took to keep it out of the
 * attribute bag.
 *
 * ## The attribute bag is partial on purpose
 *
 * `$attributes` holds only the keys the request actually sent, because a
 * profile save is a PATCH — see App\Concerns\ProfileValidationRules. That is
 * the opposite of UpdatePetContext, whose bag is written whole. Nothing here
 * fills a missing key with null.
 */
class UpdateProfileContext
{
    /**
     * Media rows attached to the avatar collection before this run uploaded
     * anything, recorded by UploadProfileImage.
     *
     * @var list<int>
     */
    protected array $previousMediaIds = [];

    /**
     * The avatar this run attached, once UploadProfileImage has run.
     */
    protected ?Media $uploadedMedia = null;

    /**
     * @param  User  $user  The account being edited; always the acting user.
     * @param  array<string, mixed>  $attributes  Only the keys the request sent.
     * @param  UploadedFile|null  $image  The new avatar, if one was uploaded.
     */
    public function __construct(
        public readonly User $user,
        public readonly array $attributes,
        public readonly ?UploadedFile $image = null,
    ) {}

    /**
     * Whether this run replaces the account's avatar.
     */
    public function hasImage(): bool
    {
        return $this->image !== null;
    }

    /**
     * The language the form asked for, or null if it carried no language
     * control.
     *
     * Deliberately not a "has it changed" predicate. ApplyLocalePreference runs
     * after PersistProfileAttributes has already written the column, so by then
     * a comparison against `$user->locale` would always say "unchanged" and the
     * cookie and session would never be updated. Presence is the condition; a
     * locale that happens to equal the stored one costs one redundant cookie.
     */
    public function locale(): ?string
    {
        $locale = $this->attributes['locale'] ?? null;

        return is_string($locale) && $locale !== '' ? $locale : null;
    }

    /**
     * @param  list<int>  $ids
     */
    public function setPreviousMediaIds(array $ids): void
    {
        $this->previousMediaIds = $ids;
    }

    /**
     * @return list<int>
     */
    public function previousMediaIds(): array
    {
        return $this->previousMediaIds;
    }

    public function setUploadedMedia(Media $media): void
    {
        $this->uploadedMedia = $media;
    }

    public function uploadedMedia(): ?Media
    {
        return $this->uploadedMedia;
    }
}
