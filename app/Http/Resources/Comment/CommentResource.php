<?php

namespace App\Http\Resources\Comment;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A comment, wherever it is rendered.
 *
 * The single comment payload in the application. It replaces
 * Http\Resources\Pet\PetCommentResource, which existed only because the pet
 * vertical shipped before this one and whose own docblock committed it to being
 * deleted here. Pet\PetDetailResource and Pet\PetCardResource now emit this
 * class, so a feed card, a detail page and the thread endpoint all describe a
 * comment identically and a client needs one reader for all three.
 *
 * ## The comment form contract
 *
 * Two keys are write shapes as well as read shapes, and are emitted under
 * exactly the name the Form Requests accept, so round-tripping a comment into
 * an edit box is a straight assignment:
 *
 * | emitted     | StoreCommentRequest | UpdateCommentRequest |
 * |-------------|---------------------|----------------------|
 * | `content`   | required, max       | required, max        |
 * | `parent_id` | nullable, integer   | — (not editable)     |
 *
 * Everything else is a read shape with no write counterpart: `id`, `author`,
 * `is_author`, `likes_count`, `is_liked`, `replies_count`, `has_reported`,
 * `replies`, `created_at`, `updated_at`.
 *
 * Neither write key carries `present`. The pet form needs it because a PUT
 * there replaces the whole listing and an omitted key is written as null; a
 * comment has one writable column and an absent `parent_id` means "top-level",
 * which is a correct answer rather than a silent wipe. See
 * .ai/rules/requests.md for when `present` is the guard and when it is noise.
 *
 * ## Counters and flags fall back rather than lazy load
 *
 * `likes_count`, `replies_count`, `is_liked` and `has_reported` come from
 * `withCount()` / `withExists()` subqueries on whatever query loaded the
 * comment. They are read with `??` and never through the relation, so a loader
 * that omits one produces a neutral value instead of an N+1 — and, on a result
 * set of one row, instead of a lazy load that Model::preventLazyLoading() would
 * not even catch (see .ai/rules/app.md).
 *
 * `is_liked` and `has_reported` are absent for a guest, because `withLikedBy()`
 * and `withReportedBy()` are no-ops for a null viewer; both default to false.
 *
 * `replies` is present only where they were loaded: the thread endpoint and the
 * detail page load a bounded few per comment, a feed card loads none, and a
 * reply carries none because threads are two levels deep by design.
 *
 * @mixin Comment
 */
class CommentResource extends JsonResource
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

            'author' => CommentAuthorResource::make($this->whenLoaded('user')),
            'is_author' => $request->user()?->getKey() === $this->user_id,

            'likes_count' => (int) ($this->likes_count ?? 0),
            'is_liked' => (bool) ($this->is_liked ?? false),
            'replies_count' => (int) ($this->replies_count ?? 0),
            'has_reported' => (bool) ($this->has_reported ?? false),

            'replies' => self::collection($this->whenLoaded('replies')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
