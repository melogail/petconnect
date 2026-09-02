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
 * `id`, `name`, `username`, `email`, `email_verified_at`, `two_factor_enabled`.
 * Six keys, all of them either public (`name`, `username`) or facts the viewer
 * needs about their own session (`email`, whether it is verified, whether 2FA
 * is on). `resources/js/types/auth.ts` types against exactly this list.
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
 * - **`avatar`**, and this one is a knowing omission rather than an oversight.
 *   `resources/js/types/auth.ts` declares it and `AppHeader.vue` /
 *   `UserInfo.vue` read it — but the model never emitted an `avatar` key
 *   either, so it has been `undefined` for as long as those components have
 *   existed and they already fall back to initials. Adding it means
 *   `getFirstMediaUrl()`, which lazy loads `media` on a model fetched by
 *   `find()` — where `Builder::hydrate()` leaves `preventLazyLoading` off, so
 *   it is a silent extra query on **every authenticated request in the
 *   application**, and every query-count assertion in the suite moves by one.
 *   That is a deliberate decision with a measurable cost, not a line to slip
 *   into a security fix. If the header avatar is wanted, it needs a
 *   `loadMissing('media')` here and a pass over the count assertions.
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
     *     two_factor_enabled: bool
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
        ];
    }
}
