<?php

namespace App\Exceptions\Pets;

/**
 * The category the pet form submitted does not exist.
 */
class CategoryNotFound extends PetTaxonomyNotFound
{
    public static function withId(int $categoryId): self
    {
        return self::forField(
            'category_id',
            __('The selected category is no longer available.'),
            ['category_id' => $categoryId],
        );
    }
}
