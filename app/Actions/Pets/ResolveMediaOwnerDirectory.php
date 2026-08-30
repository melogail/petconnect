<?php

namespace App\Actions\Pets;

use App\MediaLibrary\MediaPathGenerator;
use App\Models\Pet;

/**
 * Build the custom property that tells MediaPathGenerator where a pet's files
 * live, without making it look the owner up.
 *
 * The generator builds `media/{owner directory}/{model type}/{model id}/{media
 * id}/` from the media row alone; the owning user's `media_directory_name` has
 * to be copied onto the row at upload time or the generator falls back to a
 * database lookup for every generated URL. See .ai/rules/media-library.md.
 *
 * Returns an empty array when the owner has no directory name yet, so callers
 * can spread it into their custom properties either way and the generator's
 * fallback stays in charge of that (never expected) case.
 *
 * @see MediaPathGenerator::OWNER_DIRECTORY_PROPERTY
 */
class ResolveMediaOwnerDirectory
{
    /**
     * @return array<string, string>
     */
    public function handle(Pet $pet): array
    {
        $pet->loadMissing('user');

        $directory = $pet->user?->media_directory_name;

        if (! is_string($directory) || $directory === '') {
            return [];
        }

        return [MediaPathGenerator::OWNER_DIRECTORY_PROPERTY => $directory];
    }
}
