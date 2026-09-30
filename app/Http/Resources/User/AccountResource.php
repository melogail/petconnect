<?php

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in account as the mobile app sees it: `api.v1.me.show` and the
 * `user` half of a login or registration response.
 *
 * A projection that merges what the web splits across two resources —
 * AuthenticatedUserResource (shared Inertia prop: verification and 2FA
 * state) and ProfileFormResource (the settings form) — because a native
 * client draws the profile tab, gates the "publish" button on
 * `email_verified_at`, and pre-fills the edit form from one fetch. Keys are
 * the ProfileFormResource keys so the PATCH payload mirrors the read
 * (.ai/rules/requests.md: read and write use identical names).
 *
 * @mixin User
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'is_verified' => $this->isVerified(),
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'phone' => $this->phone,
            'bio' => $this->bio,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'location' => $this->location,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'avatar' => $this->getFirstMediaUrl('users', 'display') ?: null,
            'created_at' => $this->created_at,
        ];
    }
}
