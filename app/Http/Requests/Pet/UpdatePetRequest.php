<?php

namespace App\Http\Requests\Pet;

use App\Concerns\PetValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an edit to an existing pet listing.
 *
 * The edit form posts the whole listing, so the field rules are the store
 * rules; only the cover photo becomes optional, because leaving it alone keeps
 * the one already attached.
 *
 * Authorization lives in the controller (PetPolicy::update via
 * $this->authorize()), not here, so ownership is checked in exactly one place
 * for every pet route.
 */
class UpdatePetRequest extends FormRequest
{
    use PetValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->petRules(requiresFeaturedImage: false),
            'deletedMediaIds' => ['nullable', 'array', 'max:20'],
            'deletedMediaIds.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->petMessages();
    }

    /**
     * Photos the form asked to remove. They are resolved against this pet's own
     * collection by the pipeline, so an id from another listing is ignored.
     *
     * @return list<int>
     */
    public function deletedMediaIds(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('deletedMediaIds') ?? [];

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
