<?php

use App\Actions\Pets\ListPetCategories;
use App\Http\Resources\Pet\PetCategoryOptionResource;
use App\Models\Breed;
use App\Models\Category;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What the whole tree costs: one query for the categories, one for their icons
 * and one for their breeds, however many rows each holds.
 */
const CATEGORY_TREE_QUERY_CEILING = 3;

/**
 * Add categories carrying both an icon and breeds, which is the whole of what
 * PetCategoryOptionResource walks.
 *
 * Two is the floor, not a round number: Eloquent only arms
 * Model::preventLazyLoading() on a result set of more than one row, so a
 * one-category fixture lazy loads `media` silently and proves nothing.
 */
function seedCategoryTree(int $categories): void
{
    Category::factory()
        ->count($categories)
        ->has(Breed::factory()->count(2))
        ->create()
        ->each(function (Category $category): void {
            $category->addMedia(UploadedFile::fake()->image('icon.jpg'))
                ->toMediaCollection('categories');
        });
}

/**
 * Build the payload the pet form and the filter sheet receive, and report how
 * many queries it took.
 *
 * The tree is serialised all the way to JSON on purpose: a resource only walks
 * its nested resources when something encodes it, so stopping at toArray()
 * would leave every breed — and every icon URL below the top level —
 * unresolved and the count blind to what they cost.
 */
function countCategoryTreeQueries(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    PetCategoryOptionResource::collection(app(ListPetCategories::class)->handle())
        ->response()
        ->getContent();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $queries;
}

test('serialises the whole tree in a constant number of queries however many categories there are', function () {
    Storage::fake(config('media-library.disk_name'));
    seedCategoryTree(2);

    $atTwo = countCategoryTreeQueries();

    seedCategoryTree(3);

    $atFive = countCategoryTreeQueries();

    expect($atTwo)->toBe($atFive)
        ->and($atFive)->toBeLessThanOrEqual(CATEGORY_TREE_QUERY_CEILING);
});
