<?php

namespace App\Exceptions\Pets;

/**
 * The breed the pet form submitted does not exist, or belongs to a different
 * category than the one submitted alongside it.
 */
class BreedNotFound extends PetTaxonomyNotFound
{
    public static function withId(int $breedId, int $categoryId): self
    {
        return self::forField(
            'breed_id',
            __('The selected breed is not available for that category.'),
            ['breed_id' => $breedId, 'category_id' => $categoryId],
        );
    }
}
