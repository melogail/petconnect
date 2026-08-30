<?php

namespace App\Http\Resources\Pet;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The listing owner as a pet payload shows them: enough to render a byline and
 * link to a profile, and nothing more.
 *
 * Deliberately narrow. The legacy pet payloads embedded a full UserResource,
 * which put the owner's email, phone and exact coordinates on a public page.
 *
 * @mixin User
 */
class PetOwnerResource extends JsonResource
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
            'location' => $this->location,
            'avatar' => $this->getFirstMediaUrl('users', 'thumb') ?: null,
        ];
    }
}
