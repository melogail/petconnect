<?php

namespace App\MediaLibrary;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
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
 * persisted on the media row at upload time. The database lookup below is a
 * fallback for rows written without it and should not be relied on; it is
 * memoised per resolved owner, which is safe because AppServiceProvider binds
 * this generator as a scoped singleton, so the memo lives no longer than one
 * request and cannot survive a refreshed test database.
 */
class MediaPathGenerator implements PathGenerator
{
    /**
     * Custom property holding the owning user's `media_directory_name`, copied
     * onto the media row when the file is attached so path generation never
     * has to load the model or its owner.
     */
    public const OWNER_DIRECTORY_PROPERTY = 'owner_directory';

    /**
     * Fallback lookups already performed, keyed by "{model type}:{model id}".
     *
     * @var array<string, string|null>
     */
    protected array $ownerDirectories = [];

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
        $stored = $media->getCustomProperty(self::OWNER_DIRECTORY_PROPERTY);

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        return $this->lookUpOwnerDirectory((string) $media->model_type, $media->model_id);
    }

    /**
     * Fallback resolution for media rows missing the custom property.
     */
    protected function lookUpOwnerDirectory(string $modelType, int|string|null $modelId): ?string
    {
        if ($modelType === '' || $modelId === null) {
            return null;
        }

        $key = "{$modelType}:{$modelId}";

        if (array_key_exists($key, $this->ownerDirectories)) {
            return $this->ownerDirectories[$key];
        }

        return $this->ownerDirectories[$key] = $this->resolveOwnerDirectory($modelType, $modelId);
    }

    /**
     * Read the owning user's directory name out of the database.
     */
    protected function resolveOwnerDirectory(string $modelType, int|string $modelId): ?string
    {
        $modelClass = Relation::getMorphedModel($modelType) ?? $modelType;

        if (! is_string($modelClass) || ! is_a($modelClass, Model::class, true)) {
            return null;
        }

        if (is_a($modelClass, User::class, true)) {
            return $this->userDirectory($modelId);
        }

        $ownerId = $this->ownerIdFor($modelClass, $modelId);

        return $ownerId === null ? null : $this->userDirectory($ownerId);
    }

    /**
     * The id of the user a model belongs to, read straight from its foreign key.
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function ownerIdFor(string $modelClass, int|string $modelId): int|string|null
    {
        $instance = new $modelClass;

        if (! method_exists($instance, 'user')) {
            return null;
        }

        $relation = $instance->user();

        if (! $relation instanceof BelongsTo || ! $relation->getRelated() instanceof User) {
            return null;
        }

        return $modelClass::query()
            ->withoutGlobalScopes()
            ->whereKey($modelId)
            ->value($relation->getForeignKeyName());
    }

    protected function userDirectory(int|string $userId): ?string
    {
        $directory = User::query()->whereKey($userId)->value('media_directory_name');

        return is_string($directory) && $directory !== '' ? $directory : null;
    }
}
