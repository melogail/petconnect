<?php

namespace App\Http\Resources\Pet;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category as the pet form and the filter sheet list it, with its breeds
 * nested when they were eager loaded.
 *
 * The icon is read with getFirstMediaUrl(), so **whoever loads the Category must
 * eager load `category.media`** (or `media` on a category query).
 * Model::preventLazyLoading() will not catch a miss here: medialibrary's
 * force_lazy_loading turns the access into a loadMissing(), which the guardrail
 * permits, so the cost is a silent query per category. See .ai/rules/app.md.
 *
 * @mixin Category
 */
class PetCategoryOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
            'image' => $this->getFirstMediaUrl('categories', 'thumb') ?: null,
            'breeds' => PetBreedOptionResource::collection($this->whenLoaded('breeds')),
        ];
    }
}
