<?php

namespace App\Http\Requests\Pet;

use App\Concerns\PetValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new pet listing.
 *
 * Authorization is deliberately not done here. Every pet route authorizes
 * through PetPolicy with $this->authorize() in the controller, so there is one
 * place to look; the legacy StorePetRequest returned true from authorize() and
 * its controller never called the policy, which left listing creation open to
 * any authenticated account.
 */
class StorePetRequest extends FormRequest
{
    use PetValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->petRules(requiresFeaturedImage: true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->petMessages();
    }
}
