<?php

namespace App\Http\Requests\Pet;

use App\Enums\ListingType;
use App\Models\Breed;
use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the home feed query string.
 *
 * The feed is public, so there is nothing to authorize; the request's job is
 * to bound every filter before it reaches the query. Coordinates are all or
 * nothing, the radius is clamped to the configured window, and the taxonomy
 * ids have to exist, so the filter sheet cannot be used to probe the database.
 *
 * `listing_types` carries the string backing values of ListingType. The legacy
 * request validated them as integers, which was correct for the legacy schema:
 * its ListingType was int-backed and the varchar column held "1"/"2"/"3". The
 * rule is a string enum rule here only because the port made ListingType
 * string-backed (see .ai/rules/enums.md).
 */
class ListHomeFeedRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $hasLocation = $this->filled('latitude') || $this->filled('longitude');

        return [
            'latitude' => [$hasLocation ? 'required' : 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => [$hasLocation ? 'required' : 'nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:'.$this->minRadiusKm(), 'max:'.$this->maxRadiusKm()],

            'category_ids' => ['nullable', 'array', 'max:50'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists(Category::class, 'id')],
            'breed_ids' => ['nullable', 'array', 'max:50'],
            'breed_ids.*' => ['integer', 'distinct', Rule::exists(Breed::class, 'id')],

            'age_min' => ['nullable', 'numeric', 'min:0', 'max:'.$this->maxAgeYears()],
            'age_max' => [
                'nullable',
                'numeric',
                'min:0',
                'max:'.$this->maxAgeYears(),
                Rule::when($this->filled('age_min'), ['gte:age_min']),
            ],

            'listing_types' => ['nullable', 'array', 'max:'.count(ListingType::cases())],
            'listing_types.*' => ['distinct', Rule::enum(ListingType::class)],

            'vaccinated' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Accept the list filters as either a repeated parameter or a single value,
     * which is how a link built from one selected chip arrives.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('vaccinated') && filled($this->input('vaccinated'))) {
            $this->merge(['vaccinated' => $this->boolean('vaccinated')]);
        }

        foreach (['category_ids', 'breed_ids', 'listing_types'] as $key) {
            if ($this->has($key) && ! is_array($this->input($key))) {
                $this->merge([
                    $key => array_values(array_filter([$this->input($key)], filled(...))),
                ]);
            }
        }
    }

    /**
     * Whether the visitor supplied a usable location to search around.
     */
    public function hasCoordinates(): bool
    {
        return $this->filled('latitude') && $this->filled('longitude');
    }

    public function latitude(): ?float
    {
        return $this->hasCoordinates() ? (float) $this->validated('latitude') : null;
    }

    public function longitude(): ?float
    {
        return $this->hasCoordinates() ? (float) $this->validated('longitude') : null;
    }

    /**
     * The search radius in kilometres, or null when no location was supplied.
     */
    public function radiusKm(): ?float
    {
        if (! $this->hasCoordinates()) {
            return null;
        }

        return (float) ($this->validated('radius') ?? $this->defaultRadiusKm());
    }

    /**
     * The normalised filter bag the feed pipeline consumes.
     *
     * @return array{
     *     category_ids: list<int>,
     *     breed_ids: list<int>,
     *     age_min: float|null,
     *     age_max: float|null,
     *     listing_types: list<string>,
     *     vaccinated: bool|null
     * }
     */
    public function filters(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return [
            'category_ids' => array_map('intval', $validated['category_ids'] ?? []),
            'breed_ids' => array_map('intval', $validated['breed_ids'] ?? []),
            'age_min' => isset($validated['age_min']) ? (float) $validated['age_min'] : null,
            'age_max' => isset($validated['age_max']) ? (float) $validated['age_max'] : null,
            'listing_types' => array_map('strval', $validated['listing_types'] ?? []),
            'vaccinated' => isset($validated['vaccinated']) ? (bool) $validated['vaccinated'] : null,
        ];
    }

    public function defaultRadiusKm(): float
    {
        return (float) config('petconnect.nearby.default_radius_km', 20);
    }

    public function minRadiusKm(): float
    {
        return (float) config('petconnect.nearby.min_radius_km', 1);
    }

    public function maxRadiusKm(): float
    {
        return (float) config('petconnect.nearby.max_radius_km', 100);
    }

    /**
     * The upper bound the age filter accepts, in years.
     */
    public function maxAgeYears(): float
    {
        return (float) config('petconnect.filters.max_age_years', 15);
    }

    /**
     * Where the age slider sits before the visitor touches it.
     *
     * Every filter bound the page renders is read through this request rather
     * than half here and half inline in the controller.
     */
    public function defaultAgeMin(): float
    {
        return (float) config('petconnect.filters.default_age_min', 0);
    }

    public function defaultAgeMax(): float
    {
        return (float) config('petconnect.filters.default_age_max', 15);
    }
}
