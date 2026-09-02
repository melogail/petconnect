<?php

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\File;

/**
 * `database/data/categories.json` is the source of truth for the taxonomy
 * (.ai/rules/data.md), so the test compares the table against the file rather
 * than against a list written out here: a category added to the file and not to
 * the seeder is exactly the drift a hardcoded expectation would hide.
 *
 * Both languages are asserted because a write that dropped `name_ar` is silent
 * — every page still renders, in English, for an Arabic reader.
 *
 * @return list<array{slug: string, name: string, name_ar: string, description?: string|null, description_ar?: string|null}>
 */
function categorySeedData(): array
{
    return json_decode(File::get(database_path('data/categories.json')), true, 512, JSON_THROW_ON_ERROR);
}

test('writes every category the data file declares, in both languages', function () {
    $this->seed(CategorySeeder::class);

    $expected = categorySeedData();

    expect(Category::query()->count())->toBe(count($expected));

    foreach ($expected as $category) {
        $this->assertDatabaseHas('categories', [
            'slug' => $category['slug'],
            'name' => $category['name'],
            'name_ar' => $category['name_ar'],
            'description_ar' => $category['description_ar'] ?? null,
        ]);
    }
});

/**
 * Keyed on the unique `slug` with `updateOrCreate`, which is what lets a
 * translation be corrected in the data file and picked up by re-seeding rather
 * than failing on the unique index or needing the table dropped. The row count
 * staying flat is DatabaseSeederTest's job; what is checked here is that the
 * second run actually writes.
 */
test('restores a category whose translation has drifted rather than skipping it', function () {
    $this->seed(CategorySeeder::class);

    $category = Category::query()->firstOrFail();
    $expected = $category->only(['name', 'name_ar']);

    $category->forceFill(['name' => 'Wrong', 'name_ar' => 'خطأ'])->save();

    $this->seed(CategorySeeder::class);

    expect($category->fresh()->only(['name', 'name_ar']))->toBe($expected);
});
