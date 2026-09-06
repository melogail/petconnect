<?php

namespace App\Pipelines\Pets\BuildHomeFeed;

/**
 * The discovery filters the home feed filter sheet submits.
 *
 * A typed value object rather than a loose array, so every filter step reads a
 * declared property instead of guessing at array keys. Each list is already
 * de-duplicated and cast to int by fromArray(); `null` on a scalar filter means
 * "not filtered", which is not the same as `false` on `vaccinated`.
 *
 * `$ageCeiling` is the configured upper bound an open-ended age filter falls
 * back to. It lives here, resolved once by the Action, so ApplyAgeRangeFilter
 * reads a property like every other step instead of reaching for config().
 */
class HomeFeedFilters
{
    /**
     * @param  list<int>  $categoryIds
     * @param  list<int>  $breedIds
     * @param  list<string>  $listingTypes
     */
    public function __construct(
        public readonly array $categoryIds = [],
        public readonly array $breedIds = [],
        public readonly ?float $ageMin = null,
        public readonly ?float $ageMax = null,
        public readonly array $listingTypes = [],
        public readonly ?bool $vaccinated = null,
        public readonly float $ageCeiling = 15.0,
    ) {}

    /**
     * @param  array{
     *     category_ids?: list<int>|null,
     *     breed_ids?: list<int>|null,
     *     age_min?: float|null,
     *     age_max?: float|null,
     *     listing_types?: list<string>|null,
     *     vaccinated?: bool|null
     * }  $filters
     */
    public static function fromArray(array $filters, float $ageCeiling = 15.0): self
    {
        return new self(
            categoryIds: self::intList($filters['category_ids'] ?? []),
            breedIds: self::intList($filters['breed_ids'] ?? []),
            ageMin: isset($filters['age_min']) ? (float) $filters['age_min'] : null,
            ageMax: isset($filters['age_max']) ? (float) $filters['age_max'] : null,
            listingTypes: self::stringList($filters['listing_types'] ?? []),
            vaccinated: isset($filters['vaccinated']) ? (bool) $filters['vaccinated'] : null,
            ageCeiling: $ageCeiling,
        );
    }

    /**
     * Whether any age bound was submitted at all.
     */
    public function hasAgeRange(): bool
    {
        return $this->ageMin !== null || $this->ageMax !== null;
    }

    /**
     * The lower age bound to filter on, defaulting to newborn.
     */
    public function effectiveAgeMin(): float
    {
        return $this->ageMin ?? 0.0;
    }

    /**
     * The upper age bound to filter on, defaulting to the configured ceiling.
     *
     * An open-ended bound is resolved rather than left off, which keeps the
     * filter a single BETWEEN instead of two conditional comparisons.
     */
    public function effectiveAgeMax(): float
    {
        return $this->ageMax ?? $this->ageCeiling;
    }

    /**
     * @return list<int>
     */
    private static function intList(mixed $values): array
    {
        return is_array($values)
            ? array_values(array_unique(array_map('intval', $values)))
            : [];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $values): array
    {
        return is_array($values)
            ? array_values(array_unique(array_map('strval', $values)))
            : [];
    }
}
