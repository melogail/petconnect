<?php

namespace App\Models;

use App\MediaLibrary\ConvertibleImageTypes;
use Database\Factories\BreedFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A breed within a category; slugs are unique per category, not globally.
 *
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property string|null $name_ar
 * @property string $slug
 * @property string|null $description
 * @property string|null $description_ar
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['category_id', 'name', 'name_ar', 'slug', 'description', 'description_ar'])]
class Breed extends Model implements HasMedia
{
    /** @use HasFactory<BreedFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * A breed shows a single illustrative image.
     *
     * `acceptsMimeTypes()` restates the format list App\Nova\Breed validates
     * with at the collection, as defence in depth for any path that never went
     * through the Nova form — a console command, a seeder, an import. Both are
     * built from App\MediaLibrary\ConvertibleImageTypes, so they cannot
     * disagree.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('breeds')
            ->singleFile()
            ->acceptsMimeTypes(ConvertibleImageTypes::MIME_TYPES)
            ->useDisk(config('media-library.disk_name'));
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 160, 160)
            ->nonQueued();

        $this->addMediaConversion('display')
            ->fit(Fit::Contain, 640, 640);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Pet, $this>
     */
    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }
}
