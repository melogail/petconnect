<?php

namespace App\Http\Resources\Pet;

use App\Http\Resources\Comment\CommentResource;
use App\Models\Pet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A listing as the home feed renders it.
 *
 * Public payload: it carries the city and state a listing advertises, never the
 * street address, the coordinates or the veterinarian's contact details. Those
 * live on PetDetailResource, which the detail page serves.
 *
 * `is_liked` is only present when the query ran withLikedBy() for a signed-in
 * viewer, and `distance` only when it ran withDistance(), so both fall back to
 * their neutral value rather than triggering a lazy load.
 *
 * `comments` is a bounded preview — the newest few top-level comments and no
 * replies — while `comments_count` is the true total, so a card can render
 * "N comments" and a teaser without the feed carrying a 500-comment thread.
 * EagerLoadFeedRelations sets the bound.
 *
 * This is a read shape, not the pet form's contract: its keys are the snake_case
 * column names. PetDetailResource is the one that mirrors what the edit form
 * posts back.
 *
 * @mixin Pet
 */
class PetCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'age' => $this->age,
            'gender' => $this->gender,
            'color' => $this->color,
            'description' => $this->description,
            'status' => $this->status,
            'listing_type' => $this->listing_type,
            'price' => $this->price,
            'vaccinated' => $this->vaccinated,
            'spayed_neutered' => $this->spayed_neutered,

            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,

            'category' => PetCategoryOptionResource::make($this->whenLoaded('category')),
            'breed' => PetBreedOptionResource::make($this->whenLoaded('breed')),
            'user' => PetOwnerResource::make($this->whenLoaded('user')),
            'is_owner' => $request->user()?->getKey() === $this->user_id,

            'image' => $this->featuredPhotoUrl('thumb'),

            'likes_count' => (int) ($this->likes_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'is_liked' => (bool) ($this->is_liked ?? false),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),

            'distance' => $this->when(
                $this->distance !== null,
                fn (): float => round((float) $this->distance, 2),
            ),

            'created_at' => $this->created_at,
        ];
    }
}
