<?php

namespace App\Models;

use App\Enums\ListingType;
use App\Enums\PetStatus;
use App\Traits\HasComments;
use App\Traits\HasLikes;
use App\Traits\HasSaves;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Pet extends Model implements HasMedia
{
    use HasComments, HasFactory, HasLikes, HasSaves, InteractsWithMedia, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'status' => PetStatus::class,
        'category_id' => 'integer',
        'views' => 'integer',
        'created_at' => 'datetime',
        'breed_id' => 'integer',
        'listing_type' => ListingType::class,
        'vaccinations' => 'array',
        'medications' => 'array',
        'allergies' => 'array',
        'traits' => 'array',
        'additional_info' => 'array',
        'vaccinated' => 'boolean',
        'spayed_neutered' => 'boolean',
        'last_vet_visit' => 'date',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pets');
    }

    /**
     * ============================
     * == SCOPES
     * ============================
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', PetStatus::available);
    }

    public function scopeUnavailable(Builder $query): Builder
    {
        return $query->where('status', PetStatus::unavailable);
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
