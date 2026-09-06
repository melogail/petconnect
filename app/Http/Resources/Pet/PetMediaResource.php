<?php

namespace App\Http\Resources\Pet;

use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One photo of a listing, with the two derivatives the app renders.
 *
 * Every URL is built from the media row alone, which is what keeps
 * MediaPathGenerator from loading the model and its owner per photo.
 *
 * @mixin Media
 */
class PetMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->getUrl(),
            'thumb' => $this->getUrl('thumb'),
            'display' => $this->getUrl('display'),
            'featured' => (bool) $this->getCustomProperty(Pet::FEATURED_PROPERTY, false),
        ];
    }
}
