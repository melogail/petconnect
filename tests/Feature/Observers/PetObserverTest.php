<?php

use App\Models\Breed;
use App\Models\Category;
use App\Models\Pet;

test('assigns a slug built from the breed name when the listing is created', function () {
    $category = Category::factory()->create(['name' => 'Dogs']);
    $breed    = Breed::factory()->for($category)->create(['name' => 'Golden Retriever']);

    $pet      = Pet::factory()->for($category)->for($breed)->create();

    expect($pet->slug)->toMatch('/^golden-retriever-[a-z0-9]{8}$/');

    $this->assertDatabaseHas('pets', ['id' => $pet->getKey(), 'slug' => $pet->slug]);
});

test('falls back to the category name when the listing names no breed', function () {
    $category = Category::factory()->create(['name' => 'Cats']);

    $pet      = Pet::factory()->for($category)->create(['breed_id' => null]);

    expect($pet->slug)->toMatch('/^cats-[a-z0-9]{8}$/');
});

test('gives every listing its own slug', function () {
    $pets = Pet::factory()->count(25)->create();

    expect($pets->pluck('slug')->unique())->toHaveCount(25);
});

test('keeps a slug that was supplied explicitly', function () {
    $pet = Pet::factory()->create(['slug' => 'luna-the-good-girl']);

    expect($pet->slug)->toBe('luna-the-good-girl');
});
