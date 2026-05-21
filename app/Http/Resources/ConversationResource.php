<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
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
            'type' => $this->type,
            'last_message_at' => $this->last_message_at?->toDateTimeString(),
            'users' => $this->when(
                $this->relationLoaded('users'),
                fn () => UserResource::collection($this->users)->resolve($request),
            ),
            'last_message' => $this->when(
                $this->relationLoaded('lastMessage') && $this->lastMessage,
                fn () => MessageResource::make($this->lastMessage)->resolve($request),
            ),
            'messages' => $this->when(
                $this->relationLoaded('messages'),
                fn () => MessageResource::collection($this->messages)->resolve($request),
            ),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
