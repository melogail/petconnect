<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('update', $this->pet);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Basic Information
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'numeric'],
            'breed' => ['required', 'numeric'],
            'age' => ['required', 'numeric'],
            'gender' => ['required', 'string'],
            'color' => ['required', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric'],
            'description' => ['required', 'string'],
            'listing_type' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\ListingType::class)],
            'price' => ['required_if:listing_type,'.\App\Enums\ListingType::Sale->value, 'nullable', 'numeric', 'min:0'],
            'status' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\PetStatus::class)],

            // Location
            'location.address' => ['nullable', 'string'],
            'location.detailedAddress' => ['nullable', 'string'],
            'location.city' => ['required', 'string'],
            'location.state' => ['required', 'string'],
            'location.postalCode' => ['nullable', 'string'],
            'location.country' => ['required', 'string'],
            'location.coordinates.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location.coordinates.lng' => ['nullable', 'numeric', 'between:-180,180'],

            // Images - Reduced max size to 512KB per image to prevent POST size issues
            'images' => ['nullable', 'array', 'max:3'],
            'images.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:512'],
            'featuredImage' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:512'],

            // Health (Step 4)
            'health.status' => ['nullable', 'string'],
            'health.vaccinated' => ['nullable', 'boolean'],
            'health.spayedNeutered' => ['nullable', 'boolean'],
            'health.specialNeeds' => ['nullable', 'string'],
            'health.lastVetVisit' => ['nullable', 'date'],

            // Healthcare (Step 7) - Dynamic arrays
            'health.vaccinations' => ['nullable', 'array'],
            'health.vaccinations.*.date' => ['nullable', 'date'],
            'health.vaccinations.*.name' => ['nullable', 'string', 'max:255'],
            'health.medications' => ['nullable', 'array'],
            'health.medications.*.name' => ['nullable', 'string', 'max:255'],
            'health.medications.*.usage' => ['nullable', 'string', 'max:255'],
            'health.allergies' => ['nullable', 'array'],
            'health.allergies.*' => ['nullable', 'string', 'max:255'],
            'health.vetName' => ['nullable', 'string', 'max:255'],
            'health.vetPhone' => ['nullable', 'string', 'max:20'],

            // Personality
            'traits' => ['nullable', 'array'],
            'traits.*' => ['nullable', 'string'],

            // Additional Info
            'additionalInfo' => ['nullable', 'array'],
            'additionalInfo.*.key' => ['nullable', 'string', 'max:255'],
            'additionalInfo.*.value' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'images.max' => 'You can upload a maximum of 3 additional images.',
            'images.*.max' => 'Each image must not exceed 512KB. Please compress your images before uploading.',
            'featuredImage.max' => 'The featured image must not exceed 512KB. Please compress your image before uploading.',
        ];
    }
}
