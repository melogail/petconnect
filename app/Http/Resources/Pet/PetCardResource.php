<?php

namespace App\Http\Resources\Pet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PetCardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "age" => $this->age,
            "gender" => $this->gender,
            "type" => $this->category->name,
            "breed" => $this->breed->name,
            "location" => $this->location,
            "description" => $this->description,
            "image" => $this->getFirstMediaUrl('pets'),
            "status" => $this->status,
            "isFavorite" => $this->isFavorite,
            "vaccinated" => $this->vaccinated,
            "spayedNeutered" => $this->spayed_neutered,
            "likes" => $this->likes ?? null,
            "likesCount" => $this->likes->count() ?? 0,
            "comments" => $this->comments ?? null,
            "commentsCount" => $this->comments->count() ?? 0,

        ];
    }
}
