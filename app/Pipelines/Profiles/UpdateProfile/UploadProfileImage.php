<?php

namespace App\Pipelines\Profiles\UpdateProfile;

use App\MediaLibrary\MediaPathGenerator;
use Closure;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Attach the new avatar — and record what was there before it.
 *
 * **Upload first, clear afterwards.** The previous media ids are read before
 * anything is added, so ClearPreviousProfileImage can delete exactly those rows
 * rather than clearing the collection (which would take the new file with it).
 * If this step throws, the pipeline aborts and the clear step never runs, so
 * the account keeps the avatar it had. Read UpdateProfileContext for the
 * legacy ordering this replaces and what it cost.
 *
 * Conditional: no file, no work, and no query for the previous ids either.
 *
 * ## The owner directory is stamped on the row
 *
 * MediaPathGenerator builds `media/{owner directory}/user/{id}/{media id}/`
 * from the media row alone, and falls back to a database lookup per generated
 * URL when the `owner_directory` custom property is missing. For a user the
 * owner *is* the model, so the value is read straight off `$user` — no
 * relation, no Action, unlike Actions\Pets\ResolveMediaOwnerDirectory which
 * has to reach through `pet->user`. See .ai/rules/media-library.md.
 *
 * `hashName()` rather than the legacy `Str::ulid()`: the generated path already
 * ends in the media id, so two uploads of the same file cannot collide, and a
 * content-derived name keeps the stored file recognisable. The legacy service
 * also wrote a `profile_image => true` custom property, which nothing but its
 * own resource ever read; the collection name `users` already says what the
 * file is.
 */
class UploadProfileImage
{
    public function handle(UpdateProfileContext $context, Closure $next): mixed
    {
        if (! $context->hasImage()) {
            return $next($context);
        }

        $user = $context->user;

        /** @var list<int> $previousIds */
        $previousIds = $user->getMedia('users')
            ->map(fn (Media $media): int => (int) $media->getKey())
            ->values()
            ->all();

        $context->setPreviousMediaIds($previousIds);

        $image = $context->image;

        $context->setUploadedMedia(
            $user->addMedia($image)
                ->usingFileName($image->hashName())
                ->withCustomProperties($this->ownerDirectory($context))
                ->toMediaCollection('users')
        );

        return $next($context);
    }

    /**
     * @return array<string, string>
     */
    protected function ownerDirectory(UpdateProfileContext $context): array
    {
        $directory = $context->user->media_directory_name;

        if (! is_string($directory) || $directory === '') {
            return [];
        }

        return [MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $directory];
    }
}
