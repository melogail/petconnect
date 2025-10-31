<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Category extends Model implements HasMedia
{
    use InteractsWithMedia;


    protected $guarded = [];


    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('categories')
            ->singleFile()
            ->useDisk('public');
    }

    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class);
    }
}
