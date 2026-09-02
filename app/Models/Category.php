<?php

namespace App\Models;

use App\MediaLibrary\ConvertibleImageTypes;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * A species-level grouping of pets (dog, cat, bird...).
 *
 * @property int $id
 * @property string $name
 * @property string|null $name_ar
 * @property string $slug
 * @property string|null $description
 * @property string|null $description_ar
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'name_ar', 'slug', 'description', 'description_ar'])]
class Category extends Model implements HasMedia
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * A category shows a single illustrative image.
     *
     * `acceptsMimeTypes()` restates the format list App\Nova\Category validates
     * with at the collection, as defence in depth for any path that never went
     * through the Nova form — a console command, a seeder, an import. Both are
     * built from App\MediaLibrary\ConvertibleImageTypes, so they cannot
     * disagree.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('categories')
            ->singleFile()
            ->acceptsMimeTypes(ConvertibleImageTypes::MIME_TYPES)
            ->useDisk(config('media-library.disk_name'));
    }

    /**
     * Registered on the model rather than the collection so both the category
     * icon and any future collection get the same two sizes.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 160, 160)
            ->nonQueued();

        $this->addMediaConversion('display')
            ->fit(Fit::Contain, 640, 640);
    }

    /**
     * @return HasMany<Breed, $this>
     */
    public function breeds(): HasMany
    {
        return $this->hasMany(Breed::class);
    }

    /**
     * @return HasMany<Pet, $this>
     */
    public function pets(): HasMany
    {
        return $this->hasMany(Pet::class);
    }
}
