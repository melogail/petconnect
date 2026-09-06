<?php

namespace App\Actions\Pets;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The category/breed tree the pet form and the home feed filter sheet offer.
 *
 * Breeds and category images are both eager loaded, never lazily walked, so
 * rendering the whole tree costs three queries regardless of how many
 * categories exist. `media` is in the list because
 * PetCategoryOptionResource calls getFirstMediaUrl(): without it the tree cost
 * one extra query per category (measured: 9 queries for 7 categories).
 *
 * Those figures are Action-scoped and measured under phpunit.xml's
 * `SESSION_DRIVER=array`; a real request pays 2-3 more for the `sessions` and
 * `cache` tables while `.env` keeps the `database` drivers. See
 * .ai/rules/app.md.
 */
class ListPetCategories
{
    /**
     * @return Collection<int, Category>
     */
    public function handle(): Collection
    {
        return Category::query()
            ->with([
                'media',
                'breeds' => fn (Relation $breeds): Relation => $breeds->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();
    }
}
