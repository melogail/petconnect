<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'email' => $this->email,
            'avatar' => $this->avatar ?? null,
            'phone' => $this->phone ?? null,
            'country' => $this->country ?? null,
            'city' => $this->city ?? null,
            'state' => $this->state ?? null,
            'address' => $this->address ?? null,
            'lat' => $this->lat ?? null,
            'lng' => $this->lng ?? null,
            'timezone' => $this->timezone ?? null,
            'locale' => $this->locale ?? null,
            'is_verified' => $this->is_verified ?? false,
            'last_seen_at' => Carbon::parse($this->last_seen_at)->diffForHumans() ?? null,
            'created_at' => Carbon::parse($this->created_at)->format('M Y') ?? null,
            'can' => [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
            ],
        ];
    }
}
