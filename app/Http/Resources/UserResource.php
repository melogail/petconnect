<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? null,
            'name' => $this->name ?? null,
            'avatar' => $this->getFirstMediaUrl('users') ?? null,
            'avatar_url' => $this->getFirstMediaUrl('users') ?? null,
            'email' => $this->email ?? null,
            'email_verified_at' => $this->email_verified_at ?? null,
            'is_verified' => $this->isVerified(),
            'phone' => $this->phone ?? null,
            'address' => $this->address ?? null,
            'city' => $this->city ?? null,
            'state' => $this->state ?? null,
            'zip' => $this->zip ?? null,
            'country' => $this->country ?? null,
            'locale' => $this->locale ?? config('app.locale', 'en'),
            // TODO: Add reviews and rating
            'rating' => $this->reviews()->avg('rate') ?? null,
            'created_at' => $this->created_at ?? null,
            'updated_at' => $this->updated_at ?? null,
        ];
    }
}
