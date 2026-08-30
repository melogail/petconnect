<?php

namespace App\Http\Resources\Pet;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category as the pet form and the filter sheet list it, with its breeds
 * nested when they were eager loaded.
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
