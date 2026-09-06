<?php

use App\Models\Breed;
use App\Models\Category;
use Database\Seeders\BreedSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\File;

/**
 * `database/data/breeds.json` nests the breeds under the slug of the category
 * they belong to, and that nesting is the only thing deciding which category a
 * breed lands under. A breed filed under the wrong parent is invisible from the
 * table alone — it is a valid row — but it takes the breed out of the filter
 * sheet for the category a visitor would look in.
 *
 * @return array<string, list<array{slug: string, name: string, name_ar: string, description?: string|null, description_ar?: string|null}>>
 */
function breedSeedData(): array
{
    return json_decode(File::get(database_path('data/breeds.json')), true, 512, JSON_THROW_ON_ERROR);
}

test('files every breed under the category the data file nests it in, in both languages', function () {
    $this->seed(CategorySeeder::class);
    $this->seed(BreedSeeder::class);

    $expected = breedSeedData();

    expect(Breed::query()->count())
        ->toBe(collect($expected)->flatten(1)->count());

    foreach ($expected as $categorySlug => $breeds) {
        $category = Category::query()->where('slug', $categorySlug)->firstOrFail();

        foreach ($breeds as $breed) {
            $this->assertDatabaseHas('breeds', [
                'category_id' => $category->getKey(),
                'slug' => $breed['slug'],
                'name' => $breed['name'],
                'name_ar' => $breed['name_ar'],
            ]);
        }
    }
});

/**
 * Keyed on the composite unique (category_id, slug), so a corrected translation
 * is picked up by re-seeding instead of failing on the index. Slugs are unique
 * *per category*, not globally, which is why the key is composite.
 */
test('restores a breed whose translation has drifted rather than skipping it', function () {
    $this->seed(CategorySeeder::class);
    $this->seed(BreedSeeder::class);

    $breed = Breed::query()->firstOrFail();
    $expected = $breed->only(['name', 'name_ar']);

    $breed->forceFill(['name' => 'Wrong', 'name_ar' => 'خطأ'])->save();

    $this->seed(BreedSeeder::class);

    expect($breed->fresh()->only(['name', 'name_ar']))->toBe($expected);
});

/**
 * The ordering dependency is stated rather than assumed: run without the
 * taxonomy in place the seeder names the missing category and stops, instead of
 * writing breeds with a null parent and leaving the failure to surface as an
 * empty filter sheet.
 */
test('names the missing category and writes nothing when the taxonomy has not been seeded', function () {
    expect(fn () => $this->seed(BreedSeeder::class))
        ->toThrow(RuntimeException::class, 'Category [dogs] is missing; run CategorySeeder first.');

    expect(Breed::query()->count())->toBe(0);
});
