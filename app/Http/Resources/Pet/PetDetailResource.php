<?php

namespace App\Http\Resources\Pet;

use App\Http\Resources\BreedResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PetDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'breed' => BreedResource::make($this->breed),
            'category' => CategoryResource::make($this->category),
            'user' => UserResource::make($this->user),
            'name' => $this->name,
            'age' => $this->age,
            'gender' => $this->gender,
            'color' => $this->color,
            'weight' => $this->weight,
            'description' => $this->description,
            'listing_type' => $this->listing_type,
            'price' => $this->price ?? null,
            'status' => $this->status,
            'views' => $this->views ?? 0,
            'address' => $this->address ?? null,
            'detailed_address' => $this->detailed_address ?? null,
            'city' => $this->city ?? null,
            'state' => $this->state ?? null,
            'postal_code' => $this->postal_code ?? null,
            'country' => $this->country ?? null,
            'latitude' => $this->latitude ?? null,
            'longitude' => $this->longitude ?? null,
            'health_status' => $this->health_status ?? null,
            'vaccinated' => $this->vaccinated ?? null,
            'spayed_neutered' => $this->spayed_neutered ?? null,
            'special_needs' => $this->special_needs ?? null,
            'last_vet_visit' => $this->last_vet_visit?->format('Y-m-d'),
            'vaccinations' => $this->vaccinations ?? null,
            'medications' => $this->medications ?? null,
            'allergies' => $this->allergies ?? null,
            'vet_name' => $this->vet_name ?? null,
            'vet_phone' => $this->vet_phone ?? null,
            'traits' => $this->traits ?? null,
            'additional_info' => $this->additional_info ?? null,
            'images' => $this->getMedia('pets')->map(function ($media) {
                return [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                ];
            }),
            'feature_image' => $this->getMedia('pets')->first()?->getUrl() ?? null,
            'comments' => $this->whenLoaded('comments', function () {
                return CommentResource::collection($this->comments);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
