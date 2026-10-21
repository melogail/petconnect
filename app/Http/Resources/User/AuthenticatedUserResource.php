<?php

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in viewer, as the `auth.user` shared prop.
 *
 * ## Why this exists: `auth.user` used to be the whole row
 *
 * Http\Middleware\HandleInertiaRequests shared `$request->user()` directly, so
 * the model's own `toArray()` decided the payload — every column except the
 * four in User's `#[Hidden]`. Measured, that put `address`, `lat`, `lng`,
 * `phone`, `media_directory_name`, `two_factor_confirmed_at` and `last_seen_at`
 * into the props of **every page in the application**, the public feed and a
 * public listing included.
 *
 * Nothing leaked across users — it is the viewer's own row — but their street
 * address and exact coordinates were in the HTML of every page they loaded,
 * which is a different question from who may read them: that markup goes into
 * the browser cache, the back/forward cache, the history, a screen share, and
 * anything a future page-level cache is pointed at. A shared prop is the one
 * payload with no page-specific reason to carry anything, so it carries the
 * least it can.
 *
 * ## The whole key list, and it is the contract
 *
 * `id`, `name`, `username`, `email`, `email_verified_at`, `two_factor_enabled`,
 * `avatar`. Seven keys, all of them either public (`name`, `username`,
 * `avatar`) or facts the viewer needs about their own session (`email`,
 * whether it is verified, whether 2FA is on). `resources/js/types/auth.ts`
 * types against exactly this list.
 *
 * ## `avatar` is the header's photo, and it costs one query
 *
 * The header trigger (`shell/ShellUserMenu.vue`) and the menu's `UserInfo.vue`
 * render `auth.user.avatar` and fall back to initials when it is null. It is
 * the `thumb` crop, the same derivative UserSummaryResource ships for a byline,
 * so the header and every other avatar of this person show the same file.
 *
 * `getFirstMediaUrl()` reads the `media` relation, and the viewer comes from
 * `find()`, where `Builder::hydrate()` leaves `preventLazyLoading` off — so
 * without care it is a *silent* lazy load. `toArray()` calls
 * `loadMissing('media')` instead, which makes the cost explicit and makes it
 * exactly one query per authenticated request, and zero on a request whose
 * user already carries the relation. This was a recorded, deliberate omission
 * until the header avatar was asked for; the query-count assertions that moved
 * by one were updated in the same change.
 *
 * Only an `App\Models\User` has a photo. On a Nova request the viewer is an
 * `App\Models\Admin` on the `admin` guard, which has no `media` relation at
 * all, so it ships `avatar: null` without touching the relation — loading it
 * there throws `RelationNotFoundException` and 500s every back-office page.
 *
 * ## What is deliberately absent
 *
 * - Everything private: `phone`, `address`, `city`, `state`, `country`, `lat`,
 *   `lng`, `timezone`, `bio`, `locale`, `last_seen_at`,
 *   `media_directory_name`, `two_factor_confirmed_at`. The settings form reads
 *   those from Http\Resources\Profile\ProfileFormResource, which is served by
 *   `profile.edit` alone and is the right place for them. `locale` also rides
 *   on its own `locale` shared prop already.
 * - `location`, User's one appended attribute. It is public and coarse, but no
 *   consumer reads it off `auth.user`; Http\Resources\User\UserSummaryResource
 *   carries it for the payloads that render somebody's byline.
 *
 * ## Null is the caller's problem
 *
 * `toArray()` reads properties off the model, so this resource must never be
 * constructed around a null viewer. HandleInertiaRequests keeps the ternary:
 * `auth.user` is null for a guest, which is what every public page expects.
 *
 * @mixin User
 */
class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     username: string|null,
     *     email: string,
     *     email_verified_at: string|null,
     *     two_factor_enabled: bool,
     *     avatar: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'username'           => $this->username,
            'email'              => $this->email,
            'email_verified_at'  => $this->email_verified_at?->toIso8601String(),
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'avatar'             => $this->avatarUrl(),
        ];
    }

    /**
     * The `thumb` crop of the viewer's photo, or null when there is none or
     * the viewer is not a member account.
     */
    private function avatarUrl(): ?string
    {
        if (! $this->resource instanceof User) {
            return null;
        }

        $this->resource->loadMissing('media');

        return $this->resource->getFirstMediaUrl('users', 'thumb') ?: null;
    }
}
