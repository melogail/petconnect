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
 * The avatar is read with getFirstMediaUrl(), so **whoever loads the User must
 * eager load `user.media`**. Model::preventLazyLoading() will not catch a miss
 * here: medialibrary's force_lazy_loading turns the access into a loadMissing(),
 * which the guardrail permits, so the cost is a silent query per rendered
 * avatar (measured: 48 on a 12-card feed). See .ai/rules/app.md.
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
