<?php

namespace App\Http\Resources\Pet;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class UserProfilePetsResource extends JsonResource
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
            'name' => $this->name,
            'feature_image' => $this->getMedia('pets')->filter(function (Media $media) {
                return $media->getCustomProperty("featured") == true;
            })->first()->getUrl() ?? null,
            'type' => $this->category->name,
            'breed' => $this->breed->name,
            'age' => $this->age,
            'gender' => $this->gender,
            'color' => $this->color,
            'status' => $this->status,
            'created_at' => $this->created_at->diffForHumans(),
            'views' => $this->views
        ];
    }
}
