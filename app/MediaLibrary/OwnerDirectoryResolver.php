<?php

namespace App\MediaLibrary;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Answers "which user's directory does this media row live under?" from the
 * row's own morph columns.
 *
 * Extracted from MediaPathGenerator because two callers now need the same
 * answer for opposite reasons, and only one of them wants it to be cheap:
 *
 * - Observers\MediaOwnerDirectoryObserver asks once, on `creating`, so it can
 *   stamp MediaPathGenerator::OWNER_DIRECTORY_PROPERTY onto the row before the
 *   file is written. Two queries at upload time is the right price.
 * - MediaPathGenerator asks on every generated URL, and by then the property is
 *   expected to be there, so the lookup is a fallback that should never run.
 *
 * The memo lives here rather than in the generator. It is per instance and the
 * class is bound `scoped` in AppServiceProvider::register(), so it lasts one
 * request and cannot survive a refreshed test database — a static cache would.
 */
class OwnerDirectoryResolver
{
    /**
     * Lookups already performed, keyed by "{model type}:{model id}".
     *
     * @var array<string, string|null>
     */
    protected array $ownerDirectories = [];

    /**
     * The directory a media row belongs under: the stamped custom property when
     * it is there, the database otherwise, null for a model with no owner.
     */
    public function forMedia(Media $media): ?string
    {
        $stored = $media->getCustomProperty(MediaPathGenerator::OWNER_DIRECTORY_PROPERTY);

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        return $this->lookUp((string) $media->model_type, $media->model_id);
    }

    /**
     * Resolve a morph pair to its owning user's directory, memoised per pair.
     */
    public function lookUp(string $modelType, int|string|null $modelId): ?string
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
     *
     * `model_type` is a morph alias (.ai/rules/models.md), so it goes back
     * through the map rather than being treated as a class name.
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
     * A model with no `user()` BelongsTo — Category, Breed — has no owner and
     * costs no query to say so.
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
