<?php

use App\Enums\ListingType;
use App\Enums\PetStatus;
use App\Models\Breed;
use App\Models\Category;
use App\Models\Pet;
use Inertia\Testing\AssertableInertia as Assert;

it('shares filter options and empty active filters on the home page', function () {
    $dogs = Category::factory()->create(['name' => 'Dogs', 'slug' => 'dogs']);
    Breed::factory()->create(['category_id' => $dogs->id, 'name' => 'Labrador']);

    $this->get(route('home'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('categories.data', 1)
            ->has('categories.data.0.breeds', 1)
            ->has('listingTypes', 3)
            ->where('filters.category_ids', [])
            ->where('filters.breed_ids', [])
            ->where('filters.listing_types', [])
            ->where('filters.vaccinated', null)
            ->where('filterDefaults.age_min', 0)
            ->where('filterDefaults.age_max', 15)
        );
});

it('filters pets by category when no breed is selected', function () {
    $dogs = Category::factory()->create(['name' => 'Dogs']);
    $cats = Category::factory()->create(['name' => 'Cats']);
    $dogBreed = Breed::factory()->create(['category_id' => $dogs->id]);
    $catBreed = Breed::factory()->create(['category_id' => $cats->id]);

    $dog = Pet::factory()->create([
        'name' => 'Rex',
        'status' => PetStatus::available,
        'category_id' => $dogs->id,
        'breed_id' => $dogBreed->id,
        'age' => '3',
    ]);

    Pet::factory()->create([
        'name' => 'Misty',
        'status' => PetStatus::available,
        'category_id' => $cats->id,
        'breed_id' => $catBreed->id,
        'age' => '2',
    ]);

    $this->getJson(route('home', [
        'category_ids' => [$dogs->id],
    ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $dog->id);
});

it('filters pets by a specific breed', function () {
    $dogs = Category::factory()->create();
    $labrador = Breed::factory()->create(['category_id' => $dogs->id, 'name' => 'Labrador']);
    $husky = Breed::factory()->create(['category_id' => $dogs->id, 'name' => 'Husky']);

    $match = Pet::factory()->create([
        'status' => PetStatus::available,
        'category_id' => $dogs->id,
        'breed_id' => $labrador->id,
        'age' => '4',
    ]);

    Pet::factory()->create([
        'status' => PetStatus::available,
        'category_id' => $dogs->id,
        'breed_id' => $husky->id,
        'age' => '4',
    ]);

    $this->getJson(route('home', [
        'breed_ids' => [$labrador->id],
    ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

it('filters pets by multiple selected breeds', function () {
    $dogs = Category::factory()->create();
    $labrador = Breed::factory()->create(['category_id' => $dogs->id, 'name' => 'Labrador']);
    $husky = Breed::factory()->create(['category_id' => $dogs->id, 'name' => 'Husky']);
    $beagle = Breed::factory()->create(['category_id' => $dogs->id, 'name' => 'Beagle']);

    $labradorPet = Pet::factory()->create([
        'status' => PetStatus::available,
        'category_id' => $dogs->id,
        'breed_id' => $labrador->id,
        'age' => '4',
    ]);

    $huskyPet = Pet::factory()->create([
        'status' => PetStatus::available,
        'category_id' => $dogs->id,
        'breed_id' => $husky->id,
        'age' => '4',
    ]);

    Pet::factory()->create([
        'status' => PetStatus::available,
        'category_id' => $dogs->id,
        'breed_id' => $beagle->id,
        'age' => '4',
    ]);

    $response = $this->getJson(route('home', [
        'breed_ids' => [$labrador->id, $husky->id],
    ]));

    $response->assertSuccessful()->assertJsonCount(2, 'data');

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($labradorPet->id, $huskyPet->id);
});

it('filters pets by age range', function () {
    $young = Pet::factory()->create([
        'status' => PetStatus::available,
        'age' => '1',
    ]);

    Pet::factory()->create([
        'status' => PetStatus::available,
        'age' => '8',
    ]);

    $this->getJson(route('home', [
        'age_min' => 0,
        'age_max' => 3,
    ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $young->id);
});

it('filters pets by listing type', function () {
    $adoption = Pet::factory()->create([
        'status' => PetStatus::available,
        'listing_type' => ListingType::Adoption->value,
        'age' => '2',
    ]);

    Pet::factory()->create([
        'status' => PetStatus::available,
        'listing_type' => ListingType::Sale->value,
        'age' => '2',
    ]);

    $this->getJson(route('home', [
        'listing_types' => [ListingType::Adoption->value],
    ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $adoption->id);
});

it('filters pets by vaccinated checkbox', function () {
    $vaccinated = Pet::factory()->create([
        'status' => PetStatus::available,
        'vaccinated' => true,
        'age' => '2',
    ]);

    Pet::factory()->create([
        'status' => PetStatus::available,
        'vaccinated' => false,
        'age' => '2',
    ]);

    $this->getJson(route('home', [
        'vaccinated' => 1,
    ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $vaccinated->id);
});

it('combines category all-breeds with a specific breed from another category', function () {
    $dogs = Category::factory()->create(['name' => 'Dogs']);
    $cats = Category::factory()->create(['name' => 'Cats']);
    $dogBreed = Breed::factory()->create(['category_id' => $dogs->id]);
    $siamese = Breed::factory()->create(['category_id' => $cats->id, 'name' => 'Siamese']);
    $persian = Breed::factory()->create(['category_id' => $cats->id, 'name' => 'Persian']);

    $dog = Pet::factory()->create([
        'status' => PetStatus::available,
        'category_id' => $dogs->id,
        'breed_id' => $dogBreed->id,
        'age' => '3',
    ]);

    $siamesePet = Pet::factory()->create([
        'status' => PetStatus::available,
        'category_id' => $cats->id,
        'breed_id' => $siamese->id,
        'age' => '3',
    ]);

    Pet::factory()->create([
        'status' => PetStatus::available,
        'category_id' => $cats->id,
        'breed_id' => $persian->id,
        'age' => '3',
    ]);

    $response = $this->getJson(route('home', [
        'category_ids' => [$dogs->id],
        'breed_ids' => [$siamese->id],
    ]));

    $response->assertSuccessful()->assertJsonCount(2, 'data');

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($dog->id, $siamesePet->id);
});

it('rejects invalid filter values', function () {
    $this->getJson(route('home', [
        'category_ids' => [999999],
        'age_min' => 5,
        'age_max' => 2,
        'listing_types' => [99],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['category_ids.0', 'age_max', 'listing_types.0']);
});

it('echoes applied filters back to the inertia page', function () {
    $category = Category::factory()->create();
    $breed = Breed::factory()->create(['category_id' => $category->id]);

    $this->get(route('home', [
        'category_ids' => [$category->id],
        'breed_ids' => [$breed->id],
        'age_min' => 1,
        'age_max' => 7,
        'listing_types' => [ListingType::Sale->value],
        'vaccinated' => 1,
    ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('filters.category_ids', [$category->id])
            ->where('filters.breed_ids', [$breed->id])
            ->where('filters.age_min', 1)
            ->where('filters.age_max', 7)
            ->where('filters.listing_types', [ListingType::Sale->value])
            ->where('filters.vaccinated', true)
        );
});
