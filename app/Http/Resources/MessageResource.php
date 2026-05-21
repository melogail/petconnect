<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'conversation' => $this->when(
                $this->relationLoaded('conversation') && $this->conversation,
                fn () => ConversationSummaryResource::make($this->conversation)->resolve($request),
            ),
            'sender' => $this->when(
                $this->relationLoaded('sender') && $this->sender,
                fn () => UserResource::make($this->sender)->resolve($request),
            ),
            'pinned_by' => $this->when(
                $this->relationLoaded('pinnedBy') && $this->pinnedBy,
                fn () => UserResource::make($this->pinnedBy)->resolve($request),
            ),
            'content' => $this->content,
            'type' => $this->type,
            'status' => $this->status,
            'read_at' => $this->read_at?->toDateTimeString(),
            'is_pinned' => $this->is_pinned,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
