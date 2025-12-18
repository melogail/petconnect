<?php

namespace App\Models;

use App\Enums\ListingType;
use App\Http\Traits\HasComments;
use App\Http\Traits\HasLikes;
use App\Http\Traits\HasSaves;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Database\Eloquent\Model;

class Pet extends Model implements HasMedia
{
    use HasComments, HasLikes, HasSaves, InteractsWithMedia;

    protected $guarded = [];

    protected $casts = [
        'category_id' => 'integer',
        'breed_id' => 'integer',
        'listing_type' => ListingType::class,
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pets')
            ->multipleFiles()
            ->useDisk('public');
    }

    /**
     * =======================
     * == RELATIONSHIPS
     * =======================
     */

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function breed()
    {
        return $this->belongsTo(Breed::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
