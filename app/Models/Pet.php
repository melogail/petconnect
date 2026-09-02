<?php

namespace App\Models;

use App\Concerns\HasComments;
use App\Concerns\HasLikes;
use App\Concerns\HasSaves;
use App\Contracts\Commentable;
use App\Contracts\Likeable;
use App\Enums\HealthStatus;
use App\Enums\ListingType;
use App\Enums\PetGender;
use App\Enums\PetStatus;
use App\MediaLibrary\ConvertibleImageTypes;
use Database\Factories\PetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A pet listing.
 *
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property int|null $breed_id
 * @property string $name
 * @property string $age
 * @property PetGender $gender
 * @property string $color
 * @property string|null $weight
 * @property string $description
 * @property ListingType $listing_type
 * @property string|null $price
 * @property PetStatus $status
 * @property int $views
 * @property string|null $address
 * @property string|null $detailed_address
 * @property string $city
 * @property string $state
 * @property string|null $postal_code
 * @property string $country
 * @property string|float|null $latitude Uncast decimal(10, 8): a string on MySQL, a float on SQLite.
 * @property string|float|null $longitude Uncast decimal(11, 8): see $latitude, and PetDetailResource.
 * @property HealthStatus $health_status
 * @property bool $vaccinated
 * @property bool $spayed_neutered
 * @property string|null $special_needs
 * @property Carbon|null $last_vet_visit
 * @property list<array{name: string, date: string|null}>|null $vaccinations
 * @property list<array{name: string, usage: string|null}>|null $medications
 * @property array<int, string>|null $allergies
 * @property string|null $vet_name
 * @property string|null $vet_phone
 * @property array<int, string>|null $traits
 * @property array<string, mixed>|null $additional_info
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read float|null $distance Only present when the withDistance() scope is applied.
 */
#[Fillable([
    'user_id',
    'category_id',
    'breed_id',
    'name',
    'age',
    'gender',
    'color',
    'weight',
    'description',
    'listing_type',
    'price',
    'status',
    'address',
    'detailed_address',
    'city',
    'state',
    'postal_code',
    'country',
    'latitude',
    'longitude',
    'health_status',
    'vaccinated',
    'spayed_neutered',
    'special_needs',
    'last_vet_visit',
    'vaccinations',
    'medications',
    'allergies',
    'vet_name',
    'vet_phone',
    'traits',
    'additional_info',
])]
class Pet extends Model implements Commentable, HasMedia, Likeable
{
    /** @use HasFactory<PetFactory> */
    use HasComments, HasFactory, HasLikes, HasSaves, InteractsWithMedia, SoftDeletes;

    /**
     * The single photo collection; the cover photo is the member carrying the
     * `featured` custom property.
     */
    public const PHOTO_COLLECTION = 'pets';

    /**
     * Custom property marking the cover photo inside the photo collection.
     */
    public const FEATURED_PROPERTY = 'featured';

    /**
     * Earth's mean radius in kilometres, used by the Haversine distance.
     */
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'breed_id' => 'integer',
            'views' => 'integer',
            'gender' => PetGender::class,
            'listing_type' => ListingType::class,
            'status' => PetStatus::class,
            'health_status' => HealthStatus::class,
            'vaccinated' => 'boolean',
            'spayed_neutered' => 'boolean',
            'last_vet_visit' => 'date',
            'vaccinations' => 'array',
            'medications' => 'array',
            'allergies' => 'array',
            'traits' => 'array',
            'additional_info' => 'array',
        ];
    }

    /**
     * Photos live in one collection; the cover photo is flagged, not separated.
     *
     * `acceptsMimeTypes()` restates the validator's format list at the
     * collection, on purpose: it is the last check before a file is written and
     * the only one on a path that never went through a Form Request — a
     * console command, a seeder, an import. Both lists are built from
     * App\MediaLibrary\ConvertibleImageTypes, so they cannot disagree.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTO_COLLECTION)
            ->acceptsMimeTypes(ConvertibleImageTypes::MIME_TYPES)
            ->useDisk(config('media-library.disk_name'));
    }

    /**
     * Two derivatives so listings never serve the original upload: a square
     * card thumbnail and a bounded detail-page image.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 400, 400)
            ->nonQueued();

        $this->addMediaConversion('display')
            ->fit(Fit::Contain, 1280, 1280);
    }

    /**
     * The cover photo, or null when the pet has no featured photo.
     */
    public function featuredPhoto(): ?Media
    {
        return $this->getMedia(self::PHOTO_COLLECTION, [self::FEATURED_PROPERTY => true])->first();
    }

    /**
     * Every photo except the cover photo.
     *
     * @return MediaCollection<int, Media>
     */
    public function galleryPhotos(): MediaCollection
    {
        return $this->getMedia(self::PHOTO_COLLECTION)
            ->reject(fn (Media $photo): bool => (bool) $photo->getCustomProperty(self::FEATURED_PROPERTY, false))
            ->values();
    }

    /**
     * URL of the cover photo, falling back to the first photo of any kind.
     */
    public function featuredPhotoUrl(string $conversion = ''): ?string
    {
        $photo = $this->featuredPhoto() ?? $this->getFirstMedia(self::PHOTO_COLLECTION);

        return $photo?->getUrl($conversion) ?: null;
    }

    /**
     * The listing owner is notified when their pet is liked.
     *
     * loadMissing() rather than a bare `$this->user`: Model::preventLazyLoading()
     * is on outside production, and LikeObserver calls this on whatever instance
     * the like was written against, which is not guaranteed to carry the owner.
     *
     * @return Collection<int, User>
     */
    public function likeNotificationRecipients(): Collection
    {
        $this->loadMissing('user');

        return collect([$this->user])->filter()->values();
    }

    /**
     * The listing owner is notified when somebody comments on their pet.
     *
     * A reply notifies the comment it answers, not the owner, so this is only
     * consulted for a top-level comment;
     * Pipelines\Comments\PublishComment\NotifyCommentable drops the author
     * from the result, which is what keeps an owner's own comment silent.
     *
     * loadMissing() for the same reason likeNotificationRecipients() uses it:
     * Model::preventLazyLoading() is on outside production and the instance the
     * publish flow resolved is not guaranteed to carry the owner.
     *
     * @return Collection<int, User>
     */
    public function commentNotificationRecipients(): Collection
    {
        $this->loadMissing('user');

        return collect([$this->user])->filter()->values();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function available(Builder $query): Builder
    {
        return $query->where('status', PetStatus::Available);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function unavailable(Builder $query): Builder
    {
        return $query->where('status', PetStatus::Unavailable);
    }

    /**
     * Restrict results to pets within $radiusKm of a point.
     *
     * A bounding box narrows the candidate rows using the (latitude, longitude)
     * index, then an exact Haversine distance is applied in the WHERE clause.
     * This scope deliberately adds no SELECT and no ORDER BY, so count() and
     * paginate() keep working; use withDistance() to expose and sort by it.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function nearby(Builder $query, float $latitude, float $longitude, float $radiusKm): Builder
    {
        $latitudeDelta = $radiusKm / 111.045;
        $longitudeDelta = $radiusKm / (111.045 * max(cos(deg2rad($latitude)), 0.00001));

        $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta]);

        $this->applyLongitudeBounds($query, $longitude - $longitudeDelta, $longitude + $longitudeDelta);

        return $query->whereRaw(
            self::distanceExpression().' <= ?',
            [...self::distanceBindings($latitude, $longitude), $radiusKm],
        );
    }

    /**
     * Select the Haversine distance in kilometres as `distance` and order by it.
     *
     * Kept separate from nearby() because a raw SELECT alias cannot survive an
     * aggregate; paginate() strips both the columns and the orders before
     * counting, so pagination still works with this applied.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function withDistance(Builder $query, float $latitude, float $longitude): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($this->qualifyColumn('*'));
        }

        return $query
            ->selectRaw(
                self::distanceExpression().' as distance',
                self::distanceBindings($latitude, $longitude),
            )
            ->orderBy('distance');
    }

    /**
     * Haversine distance in kilometres between the row and a bound point.
     *
     * The cosine is clamped to [-1, 1] before acos() because floating point
     * rounding can push it marginally outside the domain, which yields NULL on
     * SQLite and NaN on MySQL. Every value is bound, never interpolated; only
     * the constant Earth radius is inlined.
     */
    private static function distanceExpression(): string
    {
        $cosine = <<<'SQL'
            cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?))
            + sin(radians(?)) * sin(radians(latitude))
            SQL;

        return sprintf(
            '(%s * acos(CASE WHEN (%s) > 1 THEN 1 WHEN (%s) < -1 THEN -1 ELSE (%s) END))',
            self::EARTH_RADIUS_KM,
            $cosine,
            $cosine,
            $cosine,
        );
    }

    /**
     * The nine positional bindings consumed by distanceExpression().
     *
     * @return array<int, float>
     */
    private static function distanceBindings(float $latitude, float $longitude): array
    {
        return [
            $latitude, $longitude, $latitude,
            $latitude, $longitude, $latitude,
            $latitude, $longitude, $latitude,
        ];
    }

    /**
     * Apply longitude bounds, splitting the range when it crosses the antimeridian.
     *
     * @param  Builder<static>  $query
     */
    private function applyLongitudeBounds(Builder $query, float $minLongitude, float $maxLongitude): void
    {
        if ($minLongitude >= -180 && $maxLongitude <= 180) {
            $query->whereBetween('longitude', [$minLongitude, $maxLongitude]);

            return;
        }

        $west = fmod($minLongitude + 540, 360) - 180;
        $east = fmod($maxLongitude + 540, 360) - 180;

        $query->where(function (Builder $bounded) use ($west, $east): void {
            $bounded
                ->where('longitude', '>=', $west)
                ->orWhere('longitude', '<=', $east);
        });
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Breed, $this>
     */
    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    /**
     * The listing owner.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
