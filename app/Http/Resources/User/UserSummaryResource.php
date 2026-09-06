<?php

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user as every other payload refers to them: enough to render a byline and
 * link to a profile, and nothing more.
 *
 * The one user-summary payload in the application. Http\Resources\Pet\PetOwnerResource
 * and Http\Resources\Comment\CommentAuthorResource were two byte-identical
 * copies of these five keys, each with a docblock committing to this class; the
 * messaging vertical would have been the third, and three copies is the point a
 * rename in one of them starts shipping a different user object per page. They
 * are now empty subclasses of this one, kept only because "the owner of this
 * listing" and "the author of this comment" are the words those payloads want
 * for the same shape — the shape itself is defined once, here.
 *
 * Deliberately narrow, and that is the whole point. The legacy app embedded a
 * full UserResource in its pet, comment and message payloads, which put the
 * subject's email, phone number and exact coordinates on a public page beside
 * every listing and every comment on it. Nothing in this class is private:
 * `location` is the coarse "City, State, Country" accessor, not `lat`/`lng`.
 *
 * The avatar is read with getFirstMediaUrl(), so **whoever loads the user must
 * eager load their `media`** — `user.media`, `users.media`, `sender.media`.
 * A miss costs one query per rendered avatar, and Model::preventLazyLoading()
 * only catches it when more than one row came back (see .ai/rules/app.md), so
 * it is the loader's job to be right rather than the guardrail's to notice.
 *
 * @mixin User
 */
class UserSummaryResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     username: string,
     *     location: string,
     *     avatar: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'location' => $this->location,
            'avatar' => $this->getFirstMediaUrl('users', 'thumb') ?: null,
        ];
    }
}
