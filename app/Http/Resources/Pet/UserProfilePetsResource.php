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
        $featuredImage = $this->getMedia('pets')->first(
            fn (Media $media) => $media->getCustomProperty('featured') === true
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'feature_image' => $featuredImage?->getUrl() ?: $this->getFirstMediaUrl('pets') ?: null,
            'type' => $this->category->name,
            'breed' => $this->breed->name,
            'age' => $this->age,
            'gender' => $this->gender,
            'color' => $this->color,
            'status' => $this->status,
            'created_at' => $this->created_at->diffForHumans(),
            'views' => $this->views,
            'can' => [
                'update' => $request->user()?->can('update', $this->resource),
                'delete' => $request->user()?->can('delete', $this->resource),
            ],
        ];
    }
}
