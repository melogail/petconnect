<?php

namespace App\Http\Resources\Pet;

use App\Models\Breed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A breed as the pet form and the filter sheet list it.
 *
 * Both names ship so the client can pick one per locale without a second
 * round trip; slugs are unique per category, not globally, so the id is what
 * the form submits.
 *
 * @mixin Breed
 */
class PetBreedOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
        ];
    }
}
