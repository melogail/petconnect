<?php

namespace App\MediaLibrary;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class MediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        $model = $media->model;
        $basename = strtolower(class_basename($model));

        if ($model instanceof User) {
            return "media/{$model->media_directory_name}/{$basename}/{$model->id}/";
        }

        $owner = $this->owningUser($model);

        if ($owner instanceof User) {
            return "media/{$owner->media_directory_name}/{$basename}/{$model->id}/";
        }

        return "media/{$basename}/{$model->id}/";
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
     * Resolve the owning user for models that store media under a user directory.
     */
    protected function owningUser(mixed $model): ?User
    {
        if (! $model instanceof Model || ! method_exists($model, 'user')) {
            return null;
        }

        $user = $model->user;

        return $user instanceof User ? $user : null;
    }
}
