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
 *
 * Asserted as an equality, not a ceiling: under a ceiling a regression of one
 * query passes silently until it happens to cross the bound, by which point the
 * commit that spent it is long gone.
 */
const CATEGORY_TREE_QUERY_COST = 3;

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
        ->and($atFive)->toBe(CATEGORY_TREE_QUERY_COST);
});

/**
 * The payload the count above is blind to. `breeds` is behind `whenLoaded()`,
 * so losing the eager load entirely drops the key and takes the query count
 * **down** rather than up (.ai/rules/tests.md) — the count agrees with the
 * regression, and only asserting the key catches it. `image` is the other one:
 * it comes from `getFirstMediaUrl()`, which medialibrary turns into a
 * `loadMissing()` that Model::preventLazyLoading() permits, so a dropped
 * `media` eager load is a silent query per category rather than a failure.
 *
 * A category with no icon reports null rather than medialibrary's empty string,
 * because the client renders a placeholder on a falsy value and `''` is not one
 * a `?? fallback` catches.
 *
 * The payload is a bare list rather than a `data` envelope: AppServiceProvider
 * calls `JsonResource::withoutWrapping()` application-wide so Inertia props read
 * as `pet.photos` rather than `pet.photos.data`.
 */
test('serialises each category with its icon and its breeds nested under it', function () {
    Storage::fake(config('media-library.disk_name'));
    seedCategoryTree(2);
    $bare = Category::factory()->has(Breed::factory()->count(2))->create(['name' => 'Zebras']);

    $payload = PetCategoryOptionResource::collection(app(ListPetCategories::class)->handle())
        ->response()
        ->getData(true);

    $withIcon = collect($payload)->firstWhere('id', Category::query()->firstOrFail()->getKey());

    expect($withIcon['image'])->toContain('icon')
        ->and($withIcon['breeds'])->toHaveCount(2)
        ->and($withIcon['breeds'][0])->toHaveKeys(['id', 'category_id', 'name', 'name_ar', 'slug'])
        ->and($withIcon['breeds'][0]['category_id'])->toBe($withIcon['id']);

    expect(collect($payload)->firstWhere('id', $bare->getKey())['image'])->toBeNull();
});

/**
 * Both the form and the filter sheet render the tree as a flat alphabetical
 * list, so the order is the Action's to settle rather than each caller's.
 */
test('orders the categories and each category breeds by name', function () {
    Category::factory()->create(['name' => 'Zebras']);
    $birds = Category::factory()->create(['name' => 'Birds']);
    Breed::factory()->for($birds)->create(['name' => 'Parrot']);
    Breed::factory()->for($birds)->create(['name' => 'Canary']);

    $tree = app(ListPetCategories::class)->handle();

    expect($tree->pluck('name')->all())->toBe(['Birds', 'Zebras'])
        ->and($tree->firstWhere('name', 'Birds')->breeds->pluck('name')->all())->toBe(['Canary', 'Parrot']);
});
