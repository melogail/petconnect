<?php

namespace App\Http\Requests;

use App\Enums\ListingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListHomePetsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hasLocation = $this->filled('latitude') || $this->filled('longitude');

        return [
            'latitude' => [
                $hasLocation ? 'required' : 'nullable',
                'numeric',
                'between:-90,90',
            ],
            'longitude' => [
                $hasLocation ? 'required' : 'nullable',
                'numeric',
                'between:-180,180',
            ],
            'radius' => [
                'nullable',
                'numeric',
                'min:'.$this->minRadiusKm(),
                'max:'.$this->maxRadiusKm(),
            ],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'breed_ids' => ['nullable', 'array'],
            'breed_ids.*' => ['integer', 'distinct', 'exists:breeds,id'],
            'age_min' => [
                'nullable',
                'numeric',
                'min:0',
                'max:'.$this->maxAgeYears(),
            ],
            'age_max' => [
                'nullable',
                'numeric',
                'min:0',
                'max:'.$this->maxAgeYears(),
                Rule::when(
                    $this->filled('age_min'),
                    ['gte:age_min']
                ),
            ],
            'listing_types' => ['nullable', 'array'],
            'listing_types.*' => ['integer', Rule::enum(ListingType::class)],
            'vaccinated' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $booleanVaccinated = $this->boolean('vaccinated');

        if ($this->has('vaccinated') && $this->input('vaccinated') !== null && $this->input('vaccinated') !== '') {
            $this->merge([
                'vaccinated' => $booleanVaccinated,
            ]);
        }

        foreach (['category_ids', 'breed_ids', 'listing_types'] as $key) {
            if ($this->has($key) && ! is_array($this->input($key))) {
                $this->merge([
                    $key => array_filter([$this->input($key)], fn ($value) => $value !== null && $value !== ''),
                ]);
            }
        }
    }

    /**
     * Whether the request includes a usable nearby search location.
     */
    public function hasCoordinates(): bool
    {
        return $this->filled('latitude') && $this->filled('longitude');
    }

    /**
     * Validated latitude when present.
     */
    public function latitude(): ?float
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        return (float) $this->validated('latitude');
    }

    /**
     * Validated longitude when present.
     */
    public function longitude(): ?float
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        return (float) $this->validated('longitude');
    }

    /**
     * Search radius in kilometers.
     */
    public function radiusKm(): float
    {
        $radius = $this->validated('radius') ?? $this->defaultRadiusKm();

        return (float) $radius;
    }

    /**
     * Normalized home-feed filter bag for the repository.
     *
     * @return array{
     *     category_ids: list<int>,
     *     breed_ids: list<int>,
     *     age_min: float|null,
     *     age_max: float|null,
     *     listing_types: list<int>,
     *     vaccinated: bool|null
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'category_ids' => array_map('intval', $validated['category_ids'] ?? []),
            'breed_ids' => array_map('intval', $validated['breed_ids'] ?? []),
            'age_min' => array_key_exists('age_min', $validated) && $validated['age_min'] !== null
                ? (float) $validated['age_min']
                : null,
            'age_max' => array_key_exists('age_max', $validated) && $validated['age_max'] !== null
                ? (float) $validated['age_max']
                : null,
            'listing_types' => array_map('intval', $validated['listing_types'] ?? []),
            'vaccinated' => array_key_exists('vaccinated', $validated)
                ? (bool) $validated['vaccinated']
                : null,
        ];
    }

    /**
     * Default nearby search radius from config.
     */
    public function defaultRadiusKm(): float
    {
        return (float) config('petconnect.nearby.default_radius_km', 20);
    }

    /**
     * Minimum allowed nearby search radius from config.
     */
    public function minRadiusKm(): float
    {
        return (float) config('petconnect.nearby.min_radius_km', 1);
    }

    /**
     * Maximum allowed nearby search radius from config.
     */
    public function maxRadiusKm(): float
    {
        return (float) config('petconnect.nearby.max_radius_km', 100);
    }

    /**
     * Maximum pet age (years) accepted by the home age filter.
     */
    public function maxAgeYears(): float
    {
        return (float) config('petconnect.filters.max_age_years', 15);
    }
}
