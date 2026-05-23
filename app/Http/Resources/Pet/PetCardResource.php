<?php

namespace App\Http\Resources\Pet;

use App\Http\Resources\CommentResource;
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
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'avatar' => $this->user?->getMedia('users')->first()?->getUrl(),
            ]),
            'ownerName' => $this->user?->name,
            'isOwnedByCurrentUser' => $this->user_id === auth()->user()?->id,
            'name' => $this->name,
            'age' => $this->age,
            'gender' => $this->gender,
            'type' => $this->category->name,
            'breed' => $this->breed->name,
            'location' => $this->location,
            'description' => $this->description,
            'image' => $this->getFirstMediaUrl('pets'),
            'status' => $this->status,
            'isFavorite' => $this->isFavorite,
            'vaccinated' => $this->vaccinated,
            'spayedNeutered' => $this->spayed_neutered,
            'likes' => $this->likes ?? null,
            'likesCount' => $this->likes_count ?? $this->likes->count() ?? 0,
            'comments' => $this->whenLoaded('comments', fn () => CommentResource::collection($this->comments)),
            'commentsCount' => $this->comments_count ?? ($this->relationLoaded('comments') ? $this->comments->count() : 0),
        ];
    }
}
