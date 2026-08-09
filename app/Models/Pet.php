<?php

namespace App\Models;

use App\Contracts\Likeable;
use App\Enums\ListingType;
use App\Enums\PetStatus;
use App\Traits\HasComments;
use App\Traits\HasLikes;
use App\Traits\HasSaves;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Pet extends Model implements HasMedia, Likeable
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
     * == CUSTOM METHODS
     * ============================
     */
    public function toggleLike(): bool
    {
        if ($this->isLikedBy(auth()->user())) {
            $this->removeLike();

            return false;
        }

        return (bool) $this->makeLike(true);
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
     * Filter pets within a radius (km) of a point using Haversine distance.
     *
     * Uses a bounding-box pre-filter, then precise geographic distance at the DB layer.
     */
    public function scopeNearby(Builder $query, float $latitude, float $longitude, float $radiusKm): Builder
    {
        $earthRadiusKm = 6371;
        $latDelta = $radiusKm / 111.045;
        $lngDelta = $radiusKm / (111.045 * max(cos(deg2rad($latitude)), 0.00001));

        $haversine = <<<'SQL'
            (%s * acos(
                CASE
                    WHEN (
                        cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
                        + sin(radians(?)) * sin(radians(latitude))
                    ) > 1 THEN 1
                    WHEN (
                        cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
                        + sin(radians(?)) * sin(radians(latitude))
                    ) < -1 THEN -1
                    ELSE (
                        cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
                        + sin(radians(?)) * sin(radians(latitude))
                    )
                END
            ))
            SQL;

        $distanceSql = sprintf($haversine, $earthRadiusKm);
        $bindings = [
            $latitude, $longitude, $latitude,
            $latitude, $longitude, $latitude,
            $latitude, $longitude, $latitude,
        ];

        $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta]);

        $this->applyLongitudeBounds($query, $longitude - $lngDelta, $longitude + $lngDelta);

        return $query
            ->selectRaw("{$distanceSql} as distance", $bindings)
            ->whereRaw("{$distanceSql} <= ?", [...$bindings, $radiusKm])
            ->orderBy('distance');
    }

    /**
     * Apply longitude bounds, including antimeridian wrap-around.
     */
    protected function applyLongitudeBounds(Builder $query, float $minLng, float $maxLng): void
    {
        if ($minLng >= -180 && $maxLng <= 180) {
            $query->whereBetween('longitude', [$minLng, $maxLng]);

            return;
        }

        $west = fmod($minLng + 540, 360) - 180;
        $east = fmod($maxLng + 540, 360) - 180;

        $query->where(function (Builder $bounded) use ($west, $east): void {
            $bounded
                ->where('longitude', '>=', $west)
                ->orWhere('longitude', '<=', $east);
        });
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

    /**
     * @return Collection<int, User>
     */
    public function likeNotificationRecipients(): Collection
    {
        return collect([$this->user])->filter();
    }
}
