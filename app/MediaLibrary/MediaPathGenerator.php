<?php

namespace App\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Stores media under the owning user's opaque `media_directory_name`, so upload
 * paths cannot be walked from a user id.
 *
 * media/{directory}/{model type}/{model id}/{media id}/ for anything owned by a
 * user, media/{model type}/{model id}/{media id}/ for global models such as
 * categories and breeds. The trailing media id keeps two uploads of the same
 * file name on one model from overwriting each other, which the legacy
 * generator allowed.
 *
 * The path is built from `media.model_type` and `media.model_id` alone. Two
 * reasons, both load bearing:
 *
 * 1. No N+1. `$media->model` is an unhydrated MorphTo — Laravel does not set
 *    the inverse relation for morphMany — so touching it costs one query per
 *    media item per URL, and reaching on to `$model->user` costs a second.
 *    Building URLs for an already eager-loaded listing page issued four extra
 *    queries for two photos before this was fixed.
 * 2. No class-name coupling. `model_type` already holds the stable morph alias
 *    registered in AppServiceProvider::configureMorphMap(); `class_basename()`
 *    would put the PHP class name into stored file paths, so renaming a model
 *    would orphan every file. See .ai/rules/models.md.
 *
 * The owner directory is read from the OWNER_DIRECTORY_PROPERTY custom property
 * persisted on the media row at upload time, and
 * Observers\MediaOwnerDirectoryObserver stamps it on `creating` for any row
 * that arrives without one — a Nova upload, a factory, a package that attaches
 * media itself — so no upload path can forget it. OwnerDirectoryResolver's
 * database fallback is therefore expected never to run here.
 */
class MediaPathGenerator implements PathGenerator
{
    /**
     * Custom property holding the owning user's `media_directory_name`, copied
     * onto the media row when the file is attached so path generation never
     * has to load the model or its owner.
     */
    public const OWNER_DIRECTORY_PROPERTY = 'owner_directory';

    public function __construct(protected readonly OwnerDirectoryResolver $ownerDirectories) {}

    public function getPath(Media $media): string
    {
        $suffix = "{$media->model_type}/{$media->model_id}/{$media->getKey()}/";

        $ownerDirectory = $this->ownerDirectory($media);

        if ($ownerDirectory === null) {
            return "media/{$suffix}";
        }

        return "media/{$ownerDirectory}/{$suffix}";
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive-images/';
    }

    /**
     * The owning user's media directory name, or null for a global model.
     */
    protected function ownerDirectory(Media $media): ?string
    {
        return $this->ownerDirectories->forMedia($media);
    }
}
