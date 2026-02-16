<?php

namespace App\Models;

use App\Enums\ListingType;
use App\Traits\HasComments;
use App\Traits\HasLikes;
use App\Traits\HasSaves;
use Carbon\Carbon;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\HasMedia;
use Illuminate\Database\Eloquent\Model;

class Pet extends Model implements HasMedia
{
    use HasComments, HasLikes, HasSaves, InteractsWithMedia;

    protected $guarded = [];

    protected $casts = [
        'category_id' => 'integer',
        'views' => 'integer',
        'created_at' => 'datetime',
        'breed_id' => 'integer',
        'listing_type' => ListingType::class,
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pets');
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
