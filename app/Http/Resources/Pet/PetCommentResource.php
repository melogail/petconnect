<?php

namespace App\Http\Resources\Pet;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A comment as a pet page renders it, with its replies nested one level deep.
 *
 * Provisional: this lives under the pet namespace because the pet feed and the
 * pet detail page both eager load the thread, and the comments vertical has
 * not been built yet. When it lands it should own a single CommentResource and
 * this class should be deleted in that change.
 *
 * `has_reported` is only present when the query ran withReportedBy() for a
 * signed-in viewer, so it defaults to false for guests.
 *
 * `replies` is likewise only present when they were loaded: the detail page
 * loads a bounded few per comment, and a feed card loads none at all.
 *
 * @mixin Comment
 */
class PetCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'parent_id' => $this->parent_id,
            'user' => PetOwnerResource::make($this->whenLoaded('user')),
            'has_reported' => (bool) ($this->has_reported ?? false),
            'replies' => self::collection($this->whenLoaded('replies')),
            'created_at' => $this->created_at,
        ];
    }
}
