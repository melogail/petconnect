<?php

namespace App\Pipelines\Pets\Shared;

use App\Exceptions\Pets\BreedNotFound;
use App\Exceptions\Pets\CategoryNotFound;
use App\Models\Breed;
use App\Models\Category;
use App\Pipelines\Pets\PetAttributeContext;
use Closure;

/**
 * Resolve the taxonomy foreign keys a pet listing is filed under, aborting the
 * flow when the category or breed named by the form no longer exists.
 *
 * `pets.category_id` is NOT NULL and restricts deletes, so an unknown category
 * has to abort rather than be created: categories are administered in Nova.
 * `breed_id` is nullable, but a breed from a *different* category would leave
 * the row internally inconsistent, so that is rejected too. The Form Request
 * already rejects unknown ids; this covers the race where the row is deleted
 * between validation and persistence.
 *
 * The work is inline rather than delegated to an Action of the same name: it
 * has one caller and nothing outside this pipeline resolves a pet's taxonomy.
 *
 * @throws CategoryNotFound
 * @throws BreedNotFound
 */
class ResolveCategoryAndBreed
{
    public function handle(PetAttributeContext $context, Closure $next): mixed
    {
        $categoryId = (int) $context->input('category_id');
        $submittedBreedId = $context->input('breed_id');

        $category = Category::query()->find($categoryId);

        if ($category === null) {
            throw CategoryNotFound::withId($categoryId);
        }

        $context->set('category_id', $category->getKey());

        if ($submittedBreedId === null) {
            $context->set('breed_id', null);

            return $next($context);
        }

        $breedId = (int) $submittedBreedId;

        $breed = Breed::query()
            ->whereKey($breedId)
            ->where('category_id', $category->getKey())
            ->first();

        if ($breed === null) {
            throw BreedNotFound::withId($breedId, $category->getKey());
        }

        $context->set('breed_id', $breed->getKey());

        return $next($context);
    }
}
