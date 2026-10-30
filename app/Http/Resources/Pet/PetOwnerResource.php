<?php

namespace App\Http\Resources\Pet;

use App\Http\Resources\User\UserSummaryResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * A listing's owner: the summary plus the `wa.me` link the owner opted into
 * from the privacy settings. Null when they did not, and null for a guest,
 * so the number is never served to somebody without an account.
 *
 * @mixin User
 */
class PetOwnerResource extends UserSummaryResource
{
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'whatsapp' => $request->user() ? $this->whatsappUrl() : null,
        ];
    }
}
