<?php

use App\Models\Breed;
use App\Models\Category;
use Database\Seeders\BreedSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\File;

it('seeds only dogs and cats categories with arabic translations from json', function () {
    $this->seed(CategorySeeder::class);

    $categories = Category::query()->orderBy('slug')->get();

    expect($categories)->toHaveCount(2)
        ->and($categories->pluck('slug')->all())->toBe(['cats', 'dogs']);

    $dogs = $categories->firstWhere('slug', 'dogs');
    $cats = $categories->firstWhere('slug', 'cats');

    expect($dogs)
        ->name->toBe('Dogs')
        ->name_ar->toBe('كلاب')
        ->description_ar->not->toBeEmpty()
        ->and($cats)
        ->name->toBe('Cats')
        ->name_ar->toBe('قطط')
        ->description_ar->not->toBeEmpty();
});

it('seeds dog and cat breeds with arabic translations from json', function () {
    $this->seed(CategorySeeder::class);
    $this->seed(BreedSeeder::class);

    $expected = json_decode(File::get(database_path('data/breeds.json')), true, 512, JSON_THROW_ON_ERROR);

    $dogs = Category::query()->where('slug', 'dogs')->firstOrFail();
    $cats = Category::query()->where('slug', 'cats')->firstOrFail();

    expect(Breed::query()->where('category_id', $dogs->id)->count())->toBe(count($expected['dogs']))
        ->and(Breed::query()->where('category_id', $cats->id)->count())->toBe(count($expected['cats']));

    $golden = Breed::query()
        ->where('category_id', $dogs->id)
        ->where('slug', 'golden-retriever')
        ->firstOrFail();

    $persian = Breed::query()
        ->where('category_id', $cats->id)
        ->where('slug', 'persian')
        ->firstOrFail();

    expect($golden)
        ->name->toBe('Golden Retriever')
        ->name_ar->toBe('جولدن ريتريفر')
        ->description_ar->not->toBeEmpty()
        ->and($persian)
        ->name->toBe('Persian')
        ->name_ar->toBe('فارسي')
        ->description_ar->not->toBeEmpty();
});

it('can be re-run without duplicating categories or breeds', function () {
    $expected = json_decode(File::get(database_path('data/breeds.json')), true, 512, JSON_THROW_ON_ERROR);
    $expectedBreedCount = count($expected['dogs']) + count($expected['cats']);

    $this->seed(CategorySeeder::class);
    $this->seed(BreedSeeder::class);
    $this->seed(CategorySeeder::class);
    $this->seed(BreedSeeder::class);

    expect(Category::query()->count())->toBe(2)
        ->and(Breed::query()->count())->toBe($expectedBreedCount);
});
