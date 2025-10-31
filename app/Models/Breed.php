<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Breed extends Model implements HasMedia
{

    use InteractsWithMedia;

    protected $guarded = [];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('breeds')
            ->singleFile()
            ->useDisk('public');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
