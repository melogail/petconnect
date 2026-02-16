<?php

namespace App\MediaLibrary;

use App\Models\User;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        if ($media->model instanceof User) {
            return 'media/' . $media->model->media_directory_name . '/' . strtolower(class_basename($media->model)) . '/' . $media->model->id . '/';
        }

        return 'media/' . $media->model->user->media_directory_name . '/' . strtolower(class_basename($media->model)) . '/' . $media->model->id . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        if ($media->model instanceof User) {
            return 'media/' . $media->model->media_directory_name . '/' . strtolower(class_basename($media->model)) . '/' . $media->model->id . '/conversions/';
        }

        return $this->getPath($media) . '/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        if ($media->model instanceof User) {
            return 'media/' . $media->model->media_directory_name . '/' . strtolower(class_basename($media->model)) . '/' . $media->model->id . '/responsive-images/';
        }

        return $this->getPath($media) . '/responsive-images/';
    }
}