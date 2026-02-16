<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Pet\UserProfilePetsResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProfileResource extends JsonResource
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
            'username' => $this->username,
            'bio' => $this->bio,
            'email' => $this->email,
            'profile_image' => $this->getMedia('users')->filter(function (Media $media) {
                return $media->getCustomProperty("profile_image") == true;
            })->first()?->getUrl(),
            'phone' => $this->phone ?? null,
            'country' => $this->country ?? null,
            'city' => $this->city ?? null,
            'state' => $this->state ?? null,
            'location' => filled($this->location) ? $this->location : null,
            'address' => $this->address ?? null,
            'lat' => $this->lat ?? null,
            'lng' => $this->lng ?? null,
            'timezone' => $this->timezone ?? null,
            'locale' => $this->locale ?? null,
            'is_verified' => $this->is_verified ?? false,
            'pets' => $this->whenLoaded('pets', function () {
                return UserProfilePetsResource::collection($this->pets);
            }),
            'last_seen_at' => Carbon::parse($this->last_seen_at)->diffForHumans() ?? null,
            'created_at' => Carbon::parse($this->created_at)->format('M Y') ?? null,
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource),
                'delete' => $request->user()?->can('delete', $this->resource),
            ],
        ];
    }
}
